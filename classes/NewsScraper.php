<?php

class NewsScraper {
    
    private $sources = [
        'DN' => [
            'https://www.dn.se/rss/',
            'https://www.dn.se/nyheter/rss/'
        ],
        'Sveriges Radio' => [
            'https://api.sr.se/api/rss/news',
            'https://sverigesradio.se/topsy/direkt/srplay.aspx?t=rss',
            'https://www.sverigesradio.se/'
        ],
        'DR' => [
            'https://www.dr.dk/nyheder/service/feeds/allenyheder',
            'https://www.dr.dk/nyheder/service/feeds/senestenyt'
        ],
        'TV2 Bornholm' => [
            'https://www.tv2bornholm.dk/rss',
            'https://www.tv2bornholm.dk/'
        ],
        'BBC News' => [
            'https://feeds.bbci.co.uk/news/rss.xml',
            'https://feeds.bbci.co.uk/news/world/rss.xml'
        ]
    ];
    
    private $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';
    
    public function getAllNews() {
        $allNews = [];
        $successfulSources = 0;
        
        foreach ($this->sources as $sourceName => $urls) {
            try {
                $news = $this->scrapeFromSource($sourceName, $urls);
                if (!empty($news)) {
                    $allNews = array_merge($allNews, $news);
                    $successfulSources++;
                    error_log("Successfully scraped " . count($news) . " articles from {$sourceName}");
                } else {
                    error_log("No articles found from {$sourceName}");
                }
            } catch (Exception $e) {
                error_log("Error scraping {$sourceName}: " . $e->getMessage());
            }
        }
        
        error_log("Total articles collected: " . count($allNews) . " from {$successfulSources} sources");
        
        // Sortera efter publiceringsdatum (nyast först)
        usort($allNews, function($a, $b) {
            return strtotime($b['pubDate']) - strtotime($a['pubDate']);
        });
        
        // Begränsa till 50 nyheter
        return array_slice($allNews, 0, 50);
    }
    
    private function scrapeFromSource($sourceName, $urls) {
        $news = [];
        
        // Om $urls är en sträng, konvertera till array
        if (is_string($urls)) {
            $urls = [$urls];
        }
        
        // Försök varje URL tills vi får nyheter
        foreach ($urls as $url) {
            try {
                // Försök först med RSS
                $rssNews = $this->tryRssFeed($sourceName, $url);
                if (!empty($rssNews)) {
                    return $rssNews;
                }
                
                // Om RSS inte fungerar, försök scrapa HTML
                $htmlNews = $this->scrapeHtml($sourceName, $url);
                if (!empty($htmlNews)) {
                    return $htmlNews;
                }
            } catch (Exception $e) {
                error_log("Failed to scrape {$url} for {$sourceName}: " . $e->getMessage());
                continue; // Försök nästa URL
            }
        }
        
        return $news; // Tom array om alla URLs misslyckades
    }
    
    private function tryRssFeed($sourceName, $url) {
        $news = [];
        
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => $this->userAgent,
                    'follow_location' => true,
                    'max_redirects' => 3,
                    'ignore_errors' => true // Viktigt för att hantera HTTP-fel
                ]
            ]);
            
            // Undertryck varningar med @
            $rssContent = @file_get_contents($url, false, $context);
            
            if ($rssContent === false) {
                // Logga felet men returnera tyst
                error_log("RSS fetch failed for {$sourceName} at {$url}");
                return [];
            }
            
            // Rensa XML-fel innan parsing
            libxml_use_internal_errors(true);
            
            // Försök läsa som XML
            $xml = @simplexml_load_string($rssContent);
            
            if ($xml === false) {
                $errors = libxml_get_errors();
                if (!empty($errors)) {
                    error_log("XML parsing errors for {$sourceName}: " . print_r($errors, true));
                }
                libxml_clear_errors();
                return [];
            }
            
            libxml_clear_errors();
            
            // Hantera olika RSS-format
            if (isset($xml->channel->item)) {
                // Standard RSS format
                foreach ($xml->channel->item as $item) {
                    $news[] = [
                        'title' => (string) $item->title,
                        'description' => $this->cleanDescription((string) $item->description, 1600),
                        'link' => (string) $item->link,
                        'pubDate' => $this->parseDate((string) $item->pubDate),
                        'source' => $sourceName
                    ];
                }
            } elseif (isset($xml->item)) {
                // Alternativt format
                foreach ($xml->item as $item) {
                    $news[] = [
                        'title' => (string) $item->title,
                        'description' => $this->cleanDescription((string) $item->description),
                        'link' => (string) $item->link,
                        'pubDate' => $this->parseDate((string) $item->pubDate),
                        'source' => $sourceName
                    ];
                }
            }
            
        } catch (Exception $e) {
            error_log("RSS Error for {$sourceName}: " . $e->getMessage());
        }
        
        return $news;
    }
    
    private function scrapeHtml($sourceName, $baseUrl) {
        $news = [];
        
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => $this->userAgent,
                    'follow_location' => true,
                    'max_redirects' => 3,
                    'ignore_errors' => true
                ]
            ]);
            
            $html = @file_get_contents($baseUrl, false, $context);
            
            if ($html === false) {
                error_log("HTML fetch failed for {$sourceName} at {$baseUrl}");
                return [];
            }
            
            // Skapa DOMDocument för HTML-parsing
            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML($html);
            libxml_clear_errors();
            
            $xpath = new DOMXPath($dom);
            
            // Olika selektorer för olika sajter
            switch ($sourceName) {
                case 'DN':
                    $news = $this->scrapeDN($xpath, $baseUrl);
                    break;
                case 'BBC News':
                    $news = $this->scrapeBBC($xpath, $baseUrl);
                    break;
                case 'DR':
                    $news = $this->scrapeDR($xpath, $baseUrl);
                    break;
                default:
                    $news = $this->scrapeGeneric($xpath, $baseUrl, $sourceName);
                    break;
            }
            
        } catch (Exception $e) {
            error_log("HTML scraping error for {$sourceName}: " . $e->getMessage());
        }
        
        return $news;
    }
    
    private function scrapeDN($xpath, $baseUrl) {
        $news = [];
        
        // Försök hitta artiklar med olika selektorer
        $articles = $xpath->query("//article | //div[contains(@class, 'article')] | //h2/a | //h3/a");
        
        $count = 0;
        foreach ($articles as $article) {
            if ($count >= 10) break;
            
            $title = '';
            $link = '';
            
            if ($article->tagName === 'a') {
                $title = trim($article->textContent);
                $link = $article->getAttribute('href');
            } else {
                $titleNode = $xpath->query(".//h2/a | .//h3/a | .//a", $article)->item(0);
                if ($titleNode) {
                    $title = trim($titleNode->textContent);
                    $link = $titleNode->getAttribute('href');
                }
            }
            
            if ($title && $link) {
                if (!$this->startsWith($link, 'http')) {
                    $link = 'https://www.dn.se' . $link;
                }
                
                $desc = $this->fetchArticleDescription($link);
                $news[] = [
                    'title' => $title,
                    'description' => $desc,
                    'link' => $link,
                    'pubDate' => date('c'),
                    'source' => 'DN'
                ];
                $count++;
            }
        }
        
        return $news;
    }
    
    private function scrapeBBC($xpath, $baseUrl) {
        $news = [];
        
        $articles = $xpath->query("//h3/a | //h2/a | //article//a");
        
        $count = 0;
        foreach ($articles as $link) {
            if ($count >= 15) break; // Ökat från 10 till 15 nyheter från BBC
            
            $title = trim($link->textContent);
            $href = $link->getAttribute('href');
            
            if ($title && $href && strlen($title) > 10) {
                if (!$this->startsWith($href, 'http')) {
                    $href = 'https://www.bbc.com' . $href;
                }
                
                $desc = $this->fetchArticleDescription($href);
                $news[] = [
                    'title' => $title,
                    'description' => $desc,
                    'link' => $href,
                    'pubDate' => date('c'),
                    'source' => 'BBC News'
                ];
                $count++;
            }
        }
        
        return $news;
    }
    
    private function scrapeDR($xpath, $baseUrl) {
        $news = [];
        
        $articles = $xpath->query("//h1/a | //h2/a | //h3/a | //article//a");
        
        $count = 0;
        foreach ($articles as $link) {
            if ($count >= 10) break;
            
            $title = trim($link->textContent);
            $href = $link->getAttribute('href');
            
            if ($title && $href && strlen($title) > 10) {
                if (!$this->startsWith($href, 'http')) {
                    $href = 'https://www.dr.dk' . $href;
                }
                
                $desc = ''; // DR använder JS-renderat innehåll, så vi skippar fetchArticleDescription för nu
                $news[] = [
                    'title' => $title,
                    'description' => $desc,
                    'link' => $href,
                    'pubDate' => date('c'),
                    'source' => 'DR'
                ];
                $count++;
            }
        }
        
        return $news;
    }
    
    private function scrapeGeneric($xpath, $baseUrl, $sourceName) {
        $news = [];
        
        // Allmän scraping för okända sajter
        $links = $xpath->query("//h1/a | //h2/a | //h3/a | //article//a[string-length(text()) > 10]");
        
        $count = 0;
        foreach ($links as $link) {
            if ($count >= 5) break;
            
            $title = trim($link->textContent);
            $href = $link->getAttribute('href');
            
            if ($title && $href && strlen($title) > 10) {
                if (!$this->startsWith($href, 'http')) {
                    $parsed = parse_url($baseUrl);
                    $href = $parsed['scheme'] . '://' . $parsed['host'] . $href;
                }
                $desc = $this->fetchArticleDescription($href);
                $news[] = [
                    'title' => $title,
                    'description' => $desc,
                    'link' => $href,
                    'pubDate' => date('c'),
                    'source' => $sourceName
                ];
                $count++;
            }
        }
        
        return $news;
    }
    
    /**
     * Rensa och trunkera en beskrivning.
     * @param string $description
     * @param int $maxLen max antal tecken (default 200)
     * @return string
     */
    private function cleanDescription($description, $maxLen = 200) {
        // Ta bort HTML-taggar och extra whitespace
        $description = strip_tags($description);
        $description = trim($description);

        // Använd multibyte-funktioner
        if (mb_strlen($description, 'UTF-8') > $maxLen) {
            $description = mb_substr($description, 0, $maxLen, 'UTF-8') . '...';
        }

        return $description;
    }

    /**
     * Försök hämta ett längre utdrag från artikelsidan.
     * Returnerar alltid en sträng (möjligen tom).
     */
    private function fetchArticleDescription($url) {
        // Försök först att hämta URL:en
        $context = stream_context_create([
            'http' => [
                'timeout' => 8,
                'user_agent' => $this->userAgent,
                'follow_location' => true,
                'max_redirects' => 3,
                'ignore_errors' => true
            ]
        ]);

        $html = @file_get_contents($url, false, $context);
        if ($html === false) {
            return '';
        }

        // Försök plocka ut meta description först
        if (preg_match('/<meta\s+name=["\']description["\']\s+content=["\']([^"\']+)["\']/i', $html, $m)) {
            return $this->cleanDescription(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), 1600);
        }

        // Annars använd DOM för att ta de första textstyckena i article eller p
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        if (@$dom->loadHTML($html) === false) {
            libxml_clear_errors();
            return '';
        }
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        // Försök flera vanliga selektorer som innehåller ingress/lede
        $selectors = [
            "//article//p[string-length(normalize-space()) > 50]",
            "//div[contains(@class,'lead')]//p[string-length(normalize-space()) > 50]",
            "//p[string-length(normalize-space()) > 80]",
            "//div[contains(@class,'article-body')]//p[string-length(normalize-space()) > 50]"
        ];

        $collected = '';
        foreach ($selectors as $sel) {
            $nodes = $xpath->query($sel);
            if ($nodes->length > 0) {
                $count = 0;
                foreach ($nodes as $node) {
                    $text = trim($node->textContent);
                    if ($text === '') continue;
                    $collected .= "\n\n" . $text;
                    $count++;
                    if ($count >= 3) break; // ta max 3 stycken
                }
            }
            if (!empty(trim($collected))) break;
        }

        if (!empty(trim($collected))) {
            return $this->cleanDescription($collected, 1600);
        }

        // Som sista utväg, ta meta property=og:description
        if (preg_match('/<meta\s+property=["\']og:description["\']\s+content=["\']([^"\']+)["\']/i', $html, $m2)) {
            return $this->cleanDescription(html_entity_decode($m2[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), 1600);
        }

        return '';
    }
    
    private function parseDate($dateString) {
        if (empty($dateString)) {
            return date('c');
        }
        
        // Försök parsa olika datumformat
        $timestamp = strtotime($dateString);
        
        if ($timestamp === false) {
            return date('c');
        }
        
        return date('c', $timestamp);
    }

    /**
     * Kompatibilitetsmetod för startsWith (PHP < 8)
     */
    private function startsWith($haystack, $needle) {
        if ($needle === '') return true;
        return mb_substr($haystack, 0, mb_strlen($needle, 'UTF-8'), 'UTF-8') === $needle;
    }
}
