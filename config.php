<?php

// Konfigurationsinställningar för nyhetssidan

return [
    // Tidszon
    'timezone' => 'Europe/Stockholm',
    
    // Maximalt antal nyheter att visa
    'max_news_items' => 50,
    
    // Timeout för HTTP-förfrågningar (sekunder)
    'http_timeout' => 10,
    
    // User agent för webbförfrågningar
    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
    
    // Nyhetskällor
    'news_sources' => [
        'DN' => [
            'name' => 'Dagens Nyheter',
            'rss_url' => 'https://www.dn.se/rss/',
            'website_url' => 'https://www.dn.se/',
            'enabled' => true
        ],
        'SR' => [
            'name' => 'Sveriges Radio',
            'rss_url' => 'https://api.sr.se/api/rss/news',
            'website_url' => 'https://www.sverigesradio.se/',
            'enabled' => true
        ],
        'DR' => [
            'name' => 'Danmarks Radio',
            'rss_url' => 'https://www.dr.dk/nyheder/service/feeds/allenyheder',
            'website_url' => 'https://www.dr.dk/nyheder',
            'enabled' => true
        ],
        'TV2_Bornholm' => [
            'name' => 'TV2 Bornholm',
            'rss_url' => 'https://www.tv2bornholm.dk/rss',
            'website_url' => 'https://www.tv2bornholm.dk/',
            'enabled' => true
        ],
        'BBC' => [
            'name' => 'BBC News',
            'rss_url' => 'https://feeds.bbci.co.uk/news/rss.xml',
            'website_url' => 'https://www.bbc.com/news',
            'enabled' => true
        ]
    ],
    
    // Väderplatser med koordinater
    'weather_locations' => [
        'Åkarp' => [
            'lat' => 55.6667,
            'lon' => 13.0833,
            'display_name' => 'Åkarp'
        ],
        'Västervik' => [
            'lat' => 57.7587,
            'lon' => 16.6370,
            'display_name' => 'Västervik'
        ],
        'Rønne' => [
            'lat' => 55.1,
            'lon' => 14.7,
            'display_name' => 'Rønne'
        ]
    ],
    
    // SMHI API inställningar
    'smhi_api' => [
        'base_url' => 'https://opendata-download-metfcst.smhi.se/api/category/pmp3g/version/2/geotype/point/',
        'timeout' => 10
    ],
    
    // Cache-inställningar (för framtida förbättringar)
    'cache' => [
        'enabled' => false,
        'duration' => 900, // 15 minuter
        'directory' => 'cache/'
    ],
    
    // Uppdateringsintervall för auto-refresh (millisekunder)
    'auto_refresh_interval' => 900000, // 15 minuter
    
    // Felsökningsinställningar
    'debug' => [
        'enabled' => true,
        'log_errors' => true,
        'show_errors' => true
    ]
];
