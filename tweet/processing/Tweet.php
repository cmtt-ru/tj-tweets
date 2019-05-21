<?php
namespace tj\tweet\processing;

use tj\tweet\exception\EntityException;
use tj\tweet\exception\ProcessingException;

class Tweet
{
    protected $data;

    public function __construct($data) {
        if (!is_array($data)) {
            throw new ProcessingException("Invalid tweet data");
        }

        $this->data = $data;

        $this->preProcess();
    }

    /**
     * Обработка твита
     *
     * @return void
     */
    public function process()
    {
        $time = time();
        $data = $this->data;

        $object = \DB\Twitter\Tweet::find_one($data['id']);

        if (!$object) {
            // Старые твиты, которых не было в базе тоже выбрасываем
            if (strtotime($data['created_at']) < time() - 24*3600) {
                throw new ProcessingException("Old tweet");
            }

            $data['parsed_entities'] = $this->getEntities();
            $has_media = (count($data['parsed_entities']) > 0);

            $object = \DB\Twitter\Tweet::create();

            $object->id = $data['id'];
            $object->created_at = strtotime($data['created_at']);
            $object->date = $time;
            $object->text = $data['text'];
            $object->parsed_text = ($data['parsed_text'] === $data['text']) ? null : $data['parsed_text'];
            $object->tweople_id = $data['user']['id'];
            $object->geo_lat = (!empty($data['coordinates']) && $data['coordinates']['type'] === 'Point') ? $data['coordinates']['coordinates'][0] : null;
            $object->geo_lon = (!empty($data['coordinates']) && $data['coordinates']['type'] === 'Point') ? $data['coordinates']['coordinates'][1] : null;
            $object->has_media = intval($has_media);
            $object->counters_last_update = $time;
            $object->quoted_status_id = (isset($data['quoted_status_id']) && $data['quoted_status_id'] > 0) ? (int) $data['quoted_status_id'] : 0;

            $object->lang = 'ru';
            if (isset($data['was_retweeted_status']) && $data['lang'] != 'ru' && preg_match('/[а-яё]+/iu', $data['user']['name']) === 0) {
                $linksInTextCount = preg_match('/(https?:\/\/t\.co\/[a-z0-9]+)/iu', $data['text']);
                if ($linksInTextCount == 0 && preg_match('/[а-яё]+/iu', $data['text']) === 0) {
                    $object->lang = null;
                } elseif ($linksInTextCount > 0) {
                    $textWOLinks = trim(preg_replace('/(https?:\/\/t\.co\/[a-z0-9]+)/iu', '', $data['text']));

                    if (mb_strlen($textWOLinks) > 0 && preg_match('/[а-яё]+/iu', $textWOLinks) === 0) {
                        $object->lang = null;
                    }
                }
            }

            if ($has_media === true) {
                foreach ($data['parsed_entities'] as $entity) {
                    $entityObject = \DB\Twitter\Media::create();

                    $entityObject->type = $entity['type'];
                    $entityObject->tweet_id = $data['id'];
                    $entityObject->service = $entity['service'];
                    $entityObject->thumbnail_url = $entity['thumbnail_url'];
                    $entityObject->media_url = $entity['media_url'];
                    $entityObject->thumbnail_width = $entity['thumbnail_width'];
                    $entityObject->thumbnail_height = $entity['thumbnail_height'];
                    $entityObject->ratio = $entity['ratio'];
                    $entityObject->save();
                }
            }

            $tweopleObject = \DB\Twitter\Tweople::raw_execute("INSERT INTO twitter_tweople (id, screen_name, name, profile_image_url, created_at, followers_count, friends_count, statuses_count)
                        VALUES (:id, :screen_name, :name, :profile_image_url, :created_at, :followers_count, :friends_count, :statuses_count)
                        ON DUPLICATE KEY UPDATE screen_name = VALUES(screen_name), name = VALUES(name), profile_image_url = VALUES(profile_image_url), created_at = VALUES(created_at), followers_count = VALUES(followers_count), friends_count = VALUES(friends_count), statuses_count = VALUES(statuses_count)",
                        ['id' => $data['user']['id'], 'screen_name' => $data['user']['screen_name'], 'name' => $data['user']['name'], 'profile_image_url' => $data['user']['profile_image_url'], 'created_at' => strtotime($data['user']['created_at']), 'followers_count' => $data['user']['followers_count'], 'friends_count' => $data['user']['friends_count'], 'statuses_count' => $data['user']['statuses_count']]);

            $sendToHalley = true;
        } else {
            if ($object->retweet_count < $data['retweet_count']) {
                $object->counters_last_update = $time;
            }
        }

        // Проставляем твитам связи с листами
        $lists = \DB\Twitter\TweopleList::select('list_id')->where_in('tweople_id', $data['users_ids'])->find_array();
        if (count($lists)) {
            $tweopleLists = [];

            foreach ($lists as $list) {
                $lId = intval($list['list_id']);
                $redisKeyByRT = "tweet:retweets:{$lId}";
                $redisKeyByTime = "tweet:feed:{$lId}";

                $tId = intval($data['id']);

                //Redis::zadd($redisKeyByRT, (int) $data['retweet_count'], $tId);
                //Redis::zadd($redisKeyByTime, $time, $tId);

                $tweopleLists[] = "({$lId}, {$tId})";
            }
            $tweopleListsStr = implode(',', $tweopleLists);

            \DB\Twitter\TweetList::raw_execute("INSERT IGNORE INTO twitter_tweets_lists (list_id, tweet_id)
                VALUES {$tweopleListsStr}");
        }

        $object->retweet_count = $data['retweet_count'];
        $object->favorite_count = $data['favorite_count'];
        $object->save();

        if (isset($sendToHalley) && $sendToHalley) {
            $data['rfcTime'] = date('r', strtotime($data['created_at']));
            $data['user']['profile_image_url_bigger'] = str_replace('_normal', '_bigger', $data['user']['profile_image_url']);
            $data['escaped_text'] = preg_replace('/\[{2}([^\|]+)\|{2}(.+?)\|{2}([^\]]+)\]{2}/i', '<a href="$1" title="$2" class="tj-tweet-url" target="_blank">$3</a>', $data['parsed_text']);
            \Halley::send('lastTweets', $data);

            if (in_array(mb_strtolower($data['user']['screen_name']), [ 'lookatme_news', 'd3ru', 'afisha', 'oldlentach', 'medialeaksru' ])) {
                \Helper::sendSlackMessage($data['text'], '#mediawatch', [
                    'username' => $data['user']['name'],
                    'icon' => $data['user']['profile_image_url']
                ]);
            }
        }
    }

    /**
     * Препроцессинг твита
     *
     * @return void
     */
    protected function preProcess()
    {
        $data = $this->data;

        if (!isset($data['id'])) {
            throw new ProcessingException("Invalid tweet data");
        }

        // Реплаи не нужны
        if (!empty($data['in_reply_to_user_id']) && mb_substr($data['text'], 0, 1) === '@') {
            throw new ProcessingException("Unimportant tweet");
        }

        $uIds = [
           $data['user']['id']
        ];

        // Обработка ретвитов
        if (isset($data['retweeted_status'])) {
            $uIds[] = $data['retweeted_status']['user']['id'];
            $data = $data['retweeted_status'];
            $data['was_retweeted_status'] = true;
        }

        $data['users_ids'] = $uIds;

        if (isset($data['extended_tweet'])) {
            $data['text'] = $data['extended_tweet']['full_text'];

            if (isset($data['extended_tweet']['entities'])) {
                $data['entities'] = $data['extended_tweet']['entities'];
            }

            if (isset($data['extended_tweet']['extended_entities'])) {
                $data['extended_entities'] = $data['extended_tweet']['extended_entities'];
            }

            unset($data['extended_tweet']);
        }

        // Обработка новых медиа
        if (isset($data['extended_entities'])) {
            if (isset($data['extended_entities']['media']) && count($data['extended_entities']['media'])) {
                $data['entities']['media'] = $data['extended_entities']['media'];
            }

            unset($data['extended_entities']);
        }

        $data['user']['profile_image_url'] = preg_replace('/^http:\/\//i', 'https://', $data['user']['profile_image_url']);

        // Парсинг сущностей для генерации закодированного текста
        $data['parsed_text'] = $data['text'];
        if (isset($data['entities'])) {
            $text = $data['text'];

            $usedLinks = [];
            foreach ($data['entities'] as $area => $items) {
                switch ($area) {
                    case 'hashtags':
                    case 'symbols':

                        break;

                    case 'urls':
                    case 'media':
                        foreach ($items as $item) {
                            if (!in_array($item['url'], $usedLinks)) {
                                $text = str_replace($item['url'], "[[{$item['url']}||{$item['expanded_url']}||{$item['display_url']}]]", $text);
                                $usedLinks[] = $item['url'];
                            }
                        }
                        break;

                    case 'user_mentions':
                        foreach ($items as $item) {
                            $text = str_replace("@{$item['screen_name']}", "[[https://twitter.com/{$item['screen_name']}||{$item['name']}||@{$item['screen_name']}]]", $text);
                        }
                        break;
                }
            }

            $data['parsed_text'] = $text;
        }

        if (isset($data['quoted_status']) && !empty($data['quoted_status'])) {
            $quoted = new Tweet($data['quoted_status']);
            $quoted->process();
            unset($quoted);
        }

        $data['is_preprocessed'] = true;

        $this->data = $data;
    }

    /**
     * Обработка сущностей
     *
     * @return array
     */
    protected function getEntities()
    {
        $entities = [];
        $data = $this->data;

        if (isset($data['entities'])) {
            if (isset($data['entities']['media'])) {
                foreach ($data['entities']['media'] as $mediaItem) {
                    try {
                        $entities[] = Entity\Media::process($mediaItem);
                    } catch (EntityException $e) {
                        //
                    }
                }
            }

            if (isset($data['entities']['urls'])) {
                foreach ($data['entities']['urls'] as $urlItem) {
                    try {
                        $entities[] = Entity\Url::process($urlItem);
                    } catch (EntityException $e) {
                        //
                    }
                }
            }
        }

        return $entities;
    }
}
