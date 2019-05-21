<?php
namespace tj\tweet\processing\entity;

use tj\tweet\exception\EntityException;

class Media
{
    public static function process($data)
    {
        if (!isset($data['type'])) {
            throw new EntityException("Invalid media entity");
        }

        switch ($data['type']) {
            case 'photo':
                $result = [
                    'type' => 1,
                    'thumbnail_url' => "{$data['media_url_https']}:medium",
                    'media_url' => "{$data['media_url_https']}:orig",
                    'thumbnail_width' => (int) $data['sizes']['medium']['w'],
                    'thumbnail_height' => (int) $data['sizes']['medium']['h'],
                    'ratio' => round($data['sizes']['medium']['w']/$data['sizes']['medium']['h'], 4),
                    'service' => 'pictwitter'
                ];
                break;

            case 'animated_gif':
                $result = [
                    'type' => 1,
                    'thumbnail_url' => "{$data['media_url_https']}:medium",
                    'media_url' => $data['video_info']['variants'][0]['url'],
                    'thumbnail_width' => (int) $data['sizes']['medium']['w'],
                    'thumbnail_height' => (int) $data['sizes']['medium']['h'],
                    'ratio' => round($data['sizes']['medium']['w']/$data['sizes']['medium']['h'], 4),
                    'service' => 'pictwittergif'
                ];
                break;
            /*
            case 'video':
                $result = [
                    'type' => 2,
                    'thumbnail_url' => "{$data['media_url_https']}:medium",
                    'media_url' => $data['video_info']['variants'][0]['url'],
                    'thumbnail_width' => (int) $data['sizes']['medium']['w'],
                    'thumbnail_height' => (int) $data['sizes']['medium']['h'],
                    'ratio' => round($data['sizes']['medium']['w']/$data['sizes']['medium']['h'], 4),
                    'service' => 'pictwittergif'
                ];
                break;
            */
        }

        if (!isset($result)) {
            throw new EntityException("Invalid media entity");
        }

        return $result;
    }
}
