<?php

class WeatherService {
    
    private $smhiBaseUrl = 'https://opendata-download-metfcst.smhi.se/api/category/pmp3g/version/2/geotype/point/';
    
    public function getWeatherForLocations($locations) {
        $weatherData = [];
        
        foreach ($locations as $locationName => $coordinates) {
            $weatherData[$locationName] = $this->getWeatherForLocation(
                $coordinates['lat'], 
                $coordinates['lon']
            );
        }
        
        return $weatherData;
    }
    
    private function getWeatherForLocation($lat, $lon) {
        try {
            // SMHI API URL
            $url = $this->smhiBaseUrl . "lon/{$lon}/lat/{$lat}/data.json";
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => 'Mozilla/5.0 (compatible; WeatherBot/1.0)',
                    'follow_location' => true,
                    'max_redirects' => 3
                ]
            ]);
            
            $response = file_get_contents($url, false, $context);
            
            if ($response === false) {
                return $this->getErrorResponse();
            }
            
            $data = json_decode($response, true);
            
            if (!$data || !isset($data['timeSeries']) || empty($data['timeSeries'])) {
                return $this->getErrorResponse();
            }
            
            // Ta den första prognosen (närmast i tid)
            $forecast = $data['timeSeries'][0];
            
            return $this->parseWeatherData($forecast);
            
        } catch (Exception $e) {
            error_log("Weather API error: " . $e->getMessage());
            return $this->getErrorResponse();
        }
    }
    
    private function parseWeatherData($forecast) {
        $parameters = [];
        
        // Konvertera parametrarna till en associativ array
        foreach ($forecast['parameters'] as $param) {
            $parameters[$param['name']] = $param['values'][0];
        }
        
        return [
            'success' => true,
            'temperature' => $parameters['t'] ?? 0, // Temperatur i Celsius
            'humidity' => $parameters['r'] ?? 0, // Relativ luftfuktighet i %
            'windSpeed' => $parameters['ws'] ?? 0, // Vindhastighet i m/s
            'windDirection' => $parameters['wd'] ?? 0, // Vindriktning i grader
            'pressure' => $parameters['msl'] ?? 0, // Lufttryck i hPa
            'visibility' => $parameters['vis'] ?? 0, // Sikt i meter
            'cloudCover' => $parameters['tcc_mean'] ?? 0, // Molntäcke (0-8)
            'precipitation' => $parameters['pmin'] ?? 0, // Nederbörd mm/h
            'time' => $forecast['validTime']
        ];
    }
    
    private function getErrorResponse() {
        return [
            'success' => false,
            'temperature' => 0,
            'humidity' => 0,
            'windSpeed' => 0,
            'windDirection' => 0,
            'pressure' => 0,
            'visibility' => 0,
            'cloudCover' => 0,
            'precipitation' => 0,
            'time' => date('c')
        ];
    }
    
    public function getWeatherDescription($cloudCover, $precipitation) {
        if ($precipitation > 0.5) {
            return 'Regn';
        } elseif ($cloudCover >= 6) {
            return 'Molnigt';
        } elseif ($cloudCover >= 3) {
            return 'Halvklart';
        } else {
            return 'Klart';
        }
    }
    
    public function getWindDirection($degrees) {
        $directions = [
            'N', 'NNO', 'NO', 'ONO',
            'O', 'OSO', 'SO', 'SSO',
            'S', 'SSV', 'SV', 'VSV',
            'V', 'VNV', 'NV', 'NNV'
        ];
        
        $index = round($degrees / 22.5) % 16;
        return $directions[$index];
    }
}
