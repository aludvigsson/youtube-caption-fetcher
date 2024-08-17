<?php

namespace Aludvigsson\YouTubeCaptionFetcher;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use SimpleXMLElement;

class YouTubeCaptionFetcher
{
    private Client $client;
    private string $languageCode;

    public function __construct(string $languageCode = 'en')
    {
        $this->client = new Client([
            'headers' => $this->getDefaultHeaders(),
            'timeout' => 20,
            'allow_redirects' => $this->getRedirectConfig(),
            'verify' => false,
        ]);

        $this->languageCode = $this->validateLanguageCode($languageCode);
    }

    /**
     * Fetch the transcript for a given YouTube video URL.
     *
     * @param string $videoUrl
     * @return array
     * @throws CaptionFetcherException
     */
    public function getTranscript(string $videoUrl): array
    {
        $this->validateUrl($videoUrl);

        try {
            $captionUrl = $this->getCaptionsBaseUrl($videoUrl);
            return $this->getSubtitles($captionUrl);
        } catch (GuzzleException $e) {
            throw new CaptionFetcherException("Failed to fetch transcript: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Get the title of a YouTube video.
     *
     * @param string $videoUrl
     * @return string
     * @throws VideoTitleNotFoundException
     */
    public function getVideoTitle(string $videoUrl): string
    {
        $this->validateUrl($videoUrl);

        try {
            $response = $this->client->get($videoUrl);
            $html = $response->getBody()->getContents();
            preg_match('/<title>(.*?)<\/title>/', $html, $matches);

            if (empty($matches[1])) {
                throw new VideoTitleNotFoundException("Video title not found for URL: {$videoUrl}");
            }

            $title = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);
            return trim(str_replace(' - YouTube', '', $title));
        } catch (GuzzleException $e) {
            throw new VideoTitleNotFoundException("Failed to get video title: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Get the captions base URL for a YouTube video.
     *
     * @param string $videoUrl
     * @return string
     * @throws CaptionUrlNotFoundException
     */
    private function getCaptionsBaseUrl(string $videoUrl): string
    {
        $response = $this->client->get($videoUrl);
        $html = $response->getBody()->getContents();
        preg_match('/"captionTracks":([^\]]*])/', $html, $matches);

        if (empty($matches[1]) || strpos($matches[1], '"baseUrl":') === false) {
            throw new CaptionUrlNotFoundException("Caption URL not found for video: {$videoUrl}");
        }

        $results = json_decode($matches[1], true);
        $result = array_filter($results, fn($result) => $result['languageCode'] == $this->languageCode);

        if (empty($result)) {
            throw new CaptionUrlNotFoundException("Caption URL not found for languageCode: {$this->languageCode}");
        }

        return $result[0]['baseUrl'] ?? throw new CaptionUrlNotFoundException("Base URL not found in caption data");
    }

    /**
     * Fetch and parse subtitles from a given URL.
     *
     * @param string $captionUrl
     * @return array
     * @throws SubtitleParsingException
     */
    private function getSubtitles(string $captionUrl): array
    {
        $response = $this->client->get($captionUrl);
        $subtitlesContent = $response->getBody()->getContents();
        return $this->parseXmlTextNodes($subtitlesContent);
    }

    /**
     * Parse XML content and extract text nodes.
     *
     * @param string $xml
     * @return array
     * @throws SubtitleParsingException
     */
    private function parseXmlTextNodes(string $xml): array
    {
        try {
            $xmlObject = new SimpleXMLElement($xml);
            $result = [];
            foreach ($xmlObject->xpath('//text') as $textNode) {
                $result[] = [
                    'time' => (string)$textNode['start'],
                    'text' => (string)$textNode,
                ];
            }
            return $result;
        } catch (\Exception $e) {
            throw new SubtitleParsingException("Failed to parse XML: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Validate and sanitize the YouTube video URL.
     *
     * @param string $url
     * @return void
     * @throws InvalidUrlException
     */
    private function validateUrl(string $url): void
    {
        if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\/(www\.)?youtube\.com\/watch\?v=/', $url)) {
            throw new InvalidUrlException("Invalid YouTube URL: {$url}");
        }
    }

    /**
     * Validate the language code provided.
     *
     * @param string $languageCode
     * @return string
     * @throws InvalidLanguageCodeException
     */
    private function validateLanguageCode(string $languageCode): string
    {
        if (!preg_match('/^[a-z]{2}$/i', $languageCode)) {
            throw new InvalidLanguageCodeException("Invalid language code: {$languageCode}");
        }

        return strtolower($languageCode);
    }

    /**
     * Get default headers for HTTP requests.
     *
     * @return array
     */
    private function getDefaultHeaders(): array
    {
        return [
            'accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
            'accept-language' => 'en-En,en;q=0.9',
            'cache-control' => 'no-cache',
            'pragma' => 'no-cache',
            'sec-ch-ua' => '"Google Chrome";v="117", "Not;A=Brand";v="8", "Chromium";v="117"',
            'sec-ch-ua-mobile' => '?0',
            'sec-ch-ua-platform' => '"Windows"',
            'sec-fetch-dest' => 'document',
            'sec-fetch-mode' => 'navigate',
            'sec-fetch-site' => 'none',
            'sec-fetch-user' => '?1',
            'upgrade-insecure-requests' => '1',
            'user-agent' => $this->getUserAgent(),
        ];
    }

    /**
     * Get redirect configuration for HTTP client.
     *
     * @return array
     */
    private function getRedirectConfig(): array
    {
        return [
            'max' => 10,
            'strict' => false,
            'referer' => false,
            'protocols' => ['http', 'https'],
            'track_redirects' => false,
        ];
    }

    /**
     * Get a dynamic user-agent string to avoid detection.
     *
     * @return string
     */
    private function getUserAgent(): string
    {
        return 'Mozilla/5.0 (Windows NT ' . rand(6, 10) . '.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/' . rand(90, 117) . '.0.0.0 Safari/537.36';
    }
}

class CaptionFetcherException extends \Exception {}
class VideoTitleNotFoundException extends CaptionFetcherException {}
class CaptionUrlNotFoundException extends CaptionFetcherException {}
class SubtitleParsingException extends CaptionFetcherException {}
class InvalidUrlException extends CaptionFetcherException {}
class InvalidLanguageCodeException extends CaptionFetcherException {}

