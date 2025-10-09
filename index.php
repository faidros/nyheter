<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Kontrollera om classes-mappen finns, annars försök hitta filerna
if (file_exists(__DIR__ . '/classes/NewsScraper.php')) {
    require_once __DIR__ . '/classes/NewsScraper.php';
    require_once __DIR__ . '/classes/WeatherService.php';
} elseif (file_exists('NewsScraper.php')) {
    require_once 'NewsScraper.php';
    require_once 'WeatherService.php';
} else {
    die('ERROR: Klassfilerna kunde inte hittas. Kontrollera att NewsScraper.php och WeatherService.php finns i classes/-mappen eller i samma mapp som index.php');
}

// Sätt tidszon
date_default_timezone_set('Europe/Stockholm');

$newsScraper = new NewsScraper();
$weatherService = new WeatherService();

// Hämta nyheter från alla källor
$news = $newsScraper->getAllNews();

// Hämta väderdata
$weatherData = $weatherService->getWeatherForLocations([
    'Åkarp' => ['lat' => 55.6667, 'lon' => 13.0833],
    'Västervik' => ['lat' => 57.7587, 'lon' => 16.6370],
    'Rønne' => ['lat' => 55.1, 'lon' => 14.7]
]);

?>
<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Samlade Nyheter</title>
    <link rel="stylesheet" href="styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <header>
            <h1><i class="fas fa-newspaper"></i> SAMLADE NYHETER</h1>
            <p class="date"><?php echo date('l, j F Y'); ?></p>
        </header>

        <div class="content">
            <main class="news-section">
                <h2><i class="fas fa-rss"></i> Senaste Nyheterna</h2>
                
                <?php if (!empty($news)): ?>
                    <div class="news-grid">
                        <?php foreach ($news as $article): ?>
                            <article class="news-item">
                                <div class="news-source"><?php echo htmlspecialchars($article['source']); ?></div>
                                <h3><a href="<?php echo htmlspecialchars($article['link']); ?>" target="_blank">
                                    <?php echo htmlspecialchars($article['title']); ?>
                                </a></h3>
                                <p class="news-description"><?php echo htmlspecialchars($article['description']); ?></p>
                                <div class="news-meta">
                                    <span class="news-time">
                                        <i class="fas fa-clock"></i>
                                        <?php echo date('H:i', strtotime($article['pubDate'])); ?>
                                    </span>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="no-news">Inga nyheter kunde hämtas just nu. Försök igen senare.</p>
                <?php endif; ?>
            </main>

            <aside class="weather-section">
                <h2><i class="fas fa-cloud-sun"></i> Väder</h2>
                
                <?php foreach ($weatherData as $location => $weather): ?>
                    <div class="weather-item">
                        <h3><?php echo htmlspecialchars($location); ?></h3>
                        <?php if ($weather['success']): ?>
                            <div class="weather-info">
                                <div class="temperature"><?php echo round($weather['temperature']); ?>°C</div>
                                <div class="weather-details">
                                    <div><i class="fas fa-tint"></i> <?php echo $weather['humidity']; ?>%</div>
                                    <div><i class="fas fa-wind"></i> <?php echo round($weather['windSpeed']); ?> m/s</div>
                                    <div><i class="fas fa-eye"></i> <?php echo round($weather['visibility'] / 1000); ?> km</div>
                                </div>
                            </div>
                        <?php else: ?>
                            <p class="weather-error">Väderdata kunde inte hämtas</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                
                <div class="weather-update">
                    <small>Uppdaterat: <?php echo date('H:i'); ?></small>
                </div>
            </aside>
        </div>

        <footer>
            <p>Data hämtas från: DN, Sveriges Radio, DR, TV2 Bornholm, BBC News och SMHI</p>
            <p>Sidan uppdaterades: <?php echo date('Y-m-d H:i:s'); ?></p>
        </footer>
    </div>

    <script>
        // Auto-refresh sidan var 15:e minut
        setTimeout(function() {
            location.reload();
        }, 15 * 60 * 1000);
    </script>
</body>
</html>
