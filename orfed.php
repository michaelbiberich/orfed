<?php

/**
 * ORFED - O'Reilly Free Ebooks Downloader
 * 
 * Downloads free ebooks from www.oreilly.com
 * 
 * Checks category pages, eg.
 * http://www.oreilly.com/programming/free
 * 
 * Searches for specific URIs, eg.
 * http://www.oreilly.com/programming/free/microservices-for-java-developers.csp
 * 
 * Download pages can be accessed directly, eg.
 * http://www.oreilly.com/programming/free/microservices-for-java-developers.csp?download=true
 * 
 * PDF, ePub and MOBI files are (usually) available, eg.
 * http://www.oreilly.com/programming/free/files/microservices-for-java-developers.mobi
 * http://www.oreilly.com/programming/free/files/microservices-for-java-developers.pdf
 * http://www.oreilly.com/programming/free/files/microservices-for-java-developers.epub
 * 
 * @version 1.0.0
 */

// FUNCTIONS

 /**
  * Check if given URI is available
  * 
  * URI is considered available, if 'HTTP/1.0 200 OK' is returned from HEAD request.
  * 
  * @param string $uri
  * 
  * @return bool
  */
function isURIAvailable(string $uri): bool
{
    try {
        $stream = fopen(
            $uri,
            'rb',
            false,
            stream_context_create(['http' => ['method' => 'HEAD']])
        );
    } catch (\ErrorException $e) {
        return false;
    }
    
    $response = stream_get_meta_data($stream);
    
    fclose($stream);
    
    foreach ($response['wrapper_data'] as $responseHeader) {
        if ($responseHeader === 'HTTP/1.0 200 OK') {
            return true;
        }
    }
    
    return false;
}

/**
 * Get files matching given regex pattern from response of HTTP request to given URI
 * 
 * @param string $pattern
 * @param string $uri
 * 
 * @return array (Empty) array of matching file tuples; eg [ ['name' => 'microservices-for-java-developers', 'path' => 'programming'], ... ]
 */
function getMatchingFilesFromResponse(string $pattern, string $uri): array
{
    $matches = [];
    $totalMatches = preg_match_all($pattern, file_get_contents($uri), $matches);
    
    if ($totalMatches === 0) {
        return [];
    }
    
    return array_map(function($path, $name) {
        return [
            'path' => $path,
            'name' => $name
        ];
    }, $matches[2], $matches[3]);
}

// CONSTANTS

define('APP_NAME', 'ORFED - O\'Reilly Free Ebooks Downloader');
define('APP_VERSION', '1.0.0');

// CONFIGURATION
$config = [
    'domain' => 'www.oreilly.com',
    'category_uri_path' => 'free',
    'categories' => [
        'business',
        'data',
        'iot',
        'programming',
        'security',
        'web-platform',
        'webops'
    ],
    'search_format' => 'csp',
    'download_formats' => [
        'epub',
        'mobi',
        'pdf'
    ],
    'search_path' => 'files',
    'download_path' => 'books'
];

// ERROR/EXCEPTION HANDLING
set_error_handler(
    function (
        int $errno,
        string $errstr,
        string $errfile,
        string $errline
    ) {
        throw new \ErrorException($errstr, $errno, 1, $errfile, $errline);
    }
);

// MAIN

// Spash screen
echo sprintf('*** %s (version %s) ***', APP_NAME, APP_VERSION),
    PHP_EOL,
    PHP_EOL;

$baseUri = 'http://' . $config['domain'];

// Check base URI
echo sprintf('INFO: Checking if base URI "%s" is available ...', $baseUri),
    PHP_EOL;

if ( ! isURIAvailable($baseUri)) {
    echo sprintf('ERROR: base URI "%s" is not available', $baseUri), PHP_EOL;
    
    exit;
}

echo sprintf('SUCCESS: base URI "%s" is available', $baseUri), PHP_EOL, PHP_EOL;

// Downloading files of all categories
echo sprintf(
        'INFO: Downloding files of %d category/categories: %s ...',
        count($config['categories']),
        implode(
            ', ', array_map(function ($category) {
                return '"' . $category . '"';
            }, $config['categories'])
        )
    ),
    PHP_EOL;
echo sprintf(
        'INFO: Downloding files of %d format(s): "%s" ...',
        count($config['download_formats']),
        implode(
            ', ', array_map(function ($format) {
                return '".' . $format . '"';
            }, $config['download_formats'])
        )
    ),
    PHP_EOL,
    PHP_EOL;

/*
Matches:

http://www.oreilly.com/business/free/the-secrets-behind-great-one-on-one-meetings.csp
    $1 -> '/'
    $2 -> 'business'
    $3 -> 'the-secrets-behind-great-one-on-one-meetings'

http://www.oreilly.com/free/critical-first-10-days-as-leader.csp?topic=business
    $1 -> ''
    $2 -> ''
    $3 -> 'critical-first-10-days-as-leader'
*/
$searchFileRegex = '/'
    . $config['domain']
    . '(\/?)([0-9a-zA-Z\-]*)\/free\/([0-9a-zA-Z\-]+)\.'
    . $config['search_format']
    . '/';

foreach ($config['categories'] as $category) {
    // Check category URI
    $categoryUri = implode(
        '/', [$baseUri, $category, $config['category_uri_path']]
    );
    
    echo sprintf(
            'INFO: Checking if category URI "%s" is available ...', $categoryUri
        ),
        PHP_EOL;

    if ( ! isURIAvailable($categoryUri)) {
        echo sprintf('ERROR: Category URI "%s" is not available', $categoryUri),
            PHP_EOL,
            PHP_EOL;
        
        continue;
    }
    
    echo sprintf('SUCCESS: Category URI "%s" is available', $categoryUri),
        PHP_EOL,
        PHP_EOL;
    
    // Searching for files of category
    echo sprintf('INFO: Searching for files of category "%s" ...', $category),
        PHP_EOL;
    
    $matchingFiles = getMatchingFilesFromResponse(
        $searchFileRegex, $categoryUri
    );
    
    if (count($matchingFiles) === 0) {
        echo sprintf('INFO: No files found for category "%s" ...', $category),
            PHP_EOL,
            PHP_EOL;
        
        continue;
    }
    
    echo sprintf(
            'SUCCESS: %d file(s) were found for category "%s"',
            count($matchingFiles),
            $category
        ),
        PHP_EOL,
        PHP_EOL;
    
    // Downloading files of category
    $downloadDirectory = implode(
        DIRECTORY_SEPARATOR, [__DIR__, $config['download_path'], $category]
    );
    
    if ( ! is_dir($downloadDirectory)) {
        mkdir($downloadDirectory, 0644, true);
    }
    
    foreach ($matchingFiles as $matchingFile) {
        $fileBaseUri = implode(
            '/',
            array_filter(
                [
                    $baseUri,
                    $matchingFile['path'] ?: $category,
                    $config['category_uri_path'],
                    $config['search_path']
                ]
            )
        );
        
        $fileDirectory = implode(
            DIRECTORY_SEPARATOR, [$downloadDirectory, $matchingFile['name']]
        );
        
        if ( ! is_dir($fileDirectory)) {
            mkdir($fileDirectory, 0644, true);
        }
        
        foreach ($config['download_formats'] as $format) {
            $fileName = $matchingFile['name'] . '.' . $format;
            $fileUri = $fileBaseUri . '/' . $fileName;
            
            echo sprintf(
                    'INFO: Checking if file "%s" is available ...', $fileName
                ),
                PHP_EOL;
            
            if ( ! isURIAvailable($fileUri)) {
                echo sprintf('ERROR: File "%s" is not available', $fileName),
                    PHP_EOL;
                
                continue;
            }
            
            echo sprintf('SUCCESS: File "%s" is available', $fileName),
               PHP_EOL;
            
            echo sprintf('INFO: Downloading file "%s" ...', $fileName),
                PHP_EOL;
            
            $result = file_put_contents(
                implode(DIRECTORY_SEPARATOR, [$fileDirectory, $fileName]),
                fopen($fileUri, 'r')
            );
            
            if ($result === false) {
                echo sprintf(
                        'ERROR: Could not download file "%s"', $fileName
                    ),
                    PHP_EOL;
                
                continue;
            }
            
            echo sprintf(
                    'SUCCESS: Downloaded file "%s"', $fileName
                ),
                PHP_EOL;
        }
        
        echo PHP_EOL;
    }
}
