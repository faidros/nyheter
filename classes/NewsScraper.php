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
                        'description' => $this->cleanDescription((string) $item->description),
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
                if (!str_starts_with($link, 'http')) {
                    $link = 'https://www.dn.se' . $link;
                }
                
                $news[] = [
                    'title' => $title,
                    'description' => '',
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
                if (!str_starts_with($href, 'http')) {
                    $href = 'https://www.bbc.com' . $href;
                }
                
                $news[] = [
                    'title' => $title,
                    'description' => '',
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
                if (!str_starts_with($href, 'http')) {
                    $href = 'https://www.dr.dk' . $href;
                }
                
                $news[] = [
                    'title' => $title,
                    'description' => '',
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
                if (!str_starts_with($href, 'http')) {
                    $parsed = parse_url($baseUrl);
                    $href = $parsed['scheme'] . '://' . $parsed['host'] . $href;
                }
                
                $news[] = [
                    'title' => $title,
                    'description' => '',
                    'link' => $href,
                    'pubDate' => date('c'),
                    'source' => $sourceName
                ];
                $count++;
            }
        }
        
        return $news;
    }
    
    private function cleanDescription($description) {
        // Ta bort HTML-taggar och extra whitespace
        $description = strip_tags($description);
        $description = trim($description);
        
        // Begränsa längden
        if (strlen($description) > 200) {
            $description = substr($description, 0, 200) . '...';
        }
        
        return $description;
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
}
