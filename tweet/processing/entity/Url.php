<?php
namespace tj\tweet\processing\entity;

use app;
use tj\tweet\exception\EntityException;

class Url
{
    public static function process($data)
    {
        if (!isset($data['expanded_url'])) {
            throw new EntityException("Invalid url entity");
        }

        $host = str_replace('www.', '', parse_url($data['expanded_url'], PHP_URL_HOST));

        switch ($host) {
            case 'youtube.com':
            case 'm.youtube.com':
            case 'youtu.be':
                if (preg_match('#^(?:https?://)?(?:www\.)?(?:m\.)?(?:youtu\.be/|youtube\.com(?:/embed/|/v/|/watch\?v=|/watch\?.+&v=))([\w-]{11})(?:.+)?$#xi', $data['expanded_url'], $matches)) {
                    $ytId = $matches[1];

                    $result = [
                        'type' => 2,
                        'thumbnail_url' => app::generateURL(sprintf("/preview/%s/%s", 'youtube', $ytId)),
                        'media_url' => "https://www.youtube.com/embed/{$ytId}",
                        'thumbnail_width' => 800,
                        'thumbnail_height' => 450,
                        'ratio' => round(800/450, 4),
                        'service' => 'youtube'
                    ];
                }
                break;
        }

        if (!isset($result)) {
            throw new EntityException("Invalid url entity");
        }

        return $result;
    }
}
