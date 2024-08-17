# YouTube Caption Fetcher

A PHP package to fetch captions from YouTube videos.

## Installation

You can install the package via composer:

```bash
composer require aludvigsson/youtube-caption-fetcher

```
## Usage

```php

use Aludvigsson\YouTubeCaptionFetcher\YouTubeCaptionFetcher;
use Aludvigsson\YouTubeCaptionFetcher\CaptionFetcherException;
use Aludvigsson\YouTubeCaptionFetcher\InvalidUrlException;

$fetcher = new YouTubeCaptionFetcher();
try {
    $transcript = $fetcher->getTranscript('https://www.youtube.com/watch?v=VIDEO_ID');
    $title = $fetcher->getVideoTitle('https://www.youtube.com/watch?v=VIDEO_ID');
    echo $title;
    print_r($transcript);
} catch (CaptionFetcherException $e) {
    echo "An error occurred: " . $e->getMessage();
}
```

