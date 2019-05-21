<?php
namespace tj\tweet;

use mc;
use R;

/**
 * Вывод топа твитов
 *
 * @author Ilya Chekalskiy <ilya@chekalskiy.ru>
 * @package TJ
 */
class TweetPack
{
    const FILTER_ALL = 0;
    const FILTER_TEXT = 1;
    const FILTER_MEDIA = 2;
    const FILTER_VIDEO = 3;

    /**
     * Составление топа твитов
     *
     * @param  array $data
     * @return array
     */
    public static function getTop($data)
    {
        $listId = (isset($data['listId'])) ? intval($data['listId']) : 0;
        $idUser = R::get('idUser');

        if (!$listId) {
            return false;
        }

        // Фильтрация по времени
        if (isset($data['interval'])) {
            $interval = self::getInterval($data['interval']);
            $mcIntervalKey = md5(serialize($data['interval']));
        } else {
            $interval = self::getInterval(false);
            $mcIntervalKey = 'empty';
        }

        $mcFilterKey = (isset($data['filter'])) ? strval($data['filter']) : '0';

        if (isset($data['onlyLang'])) {
            $lang = $data['onlyLang'];
            $mcFilterKey .= $lang;
        }

        $tweets = mc::get('tweetsPack' . $listId . $mcIntervalKey . $mcFilterKey);

        if (!mc::isResultOK()) {
            $stopList = \DB\Twitter\TweopleStopList::select('tweople_id')->where('list_id', $listId)->find_array();

            $stopListIds = [];
            if (count($stopList)) {
                foreach ($stopList as $stopListUser) {
                    $stopListIds[] = $stopListUser['tweople_id'];
                }
            }

            $tweets = \DB\Twitter\Tweet::select('twitter_tweets.*')
                ->inner_join('twitter_tweets_lists', ['twitter_tweets_lists.tweet_id', '=', 'twitter_tweets.id'])
                ->where('twitter_tweets_lists.list_id', $listId)
                ->where('deleted', 0);

            if (count($stopListIds)) {
                $tweets->where_not_in('tweople_id', $stopListIds);
            }

            $tweets->where_gte('date', $interval[0])
                ->where_lte('created_at', $interval[1])
                ->where_gte('created_at', $interval[0] - 24 * 3600)
                ->where_gt('retweet_count', 0)
                ->order_by_desc('retweet_count')
                ->order_by_asc('date')
                ->limit(500);

            if (isset($lang) && $lang !== null) {
                $tweets->where('lang', $lang);
            }

            // Фильтрация по прикреплениям
            if (isset($data['filter'])) {
                switch ($data['filter']) {
                    case self::FILTER_TEXT:
                        $tweets->where('has_media', 0);
                        break;
                    case self::FILTER_MEDIA:
                    case self::FILTER_VIDEO:
                        $tweets->where('has_media', 1);
                        break;
                }
            }

            // Execute
            $tweets = $tweets->find_array();

            if (count($tweets)) {
                $tweetsIds = $tweopleIds = $quotedTweetsIds = $quotedTweetsData = [];
                foreach ($tweets as $i => $tweet) {
                    if ($tweet['has_media'] == 1) {
                        $tweetsIds[] = $tweet['id'];
                    }

                    if ($tweet['quoted_status_id'] > 0) {
                        $quotedTweetsIds[] = $tweet['quoted_status_id'];
                    }

                    $tweopleIds[] = $tweet['tweople_id'];
                }

                // Медиаданные
                if (count($tweetsIds)) {
                    $media = \DB\Twitter\Media::where_in('tweet_id', $tweetsIds)->find_array();

                    $mediaPack = [];
                    foreach ($media as $mediaItem) {
                        $tweetId = $mediaItem['tweet_id'];
                        unset($mediaItem['id'], $mediaItem['tweet_id']);
                        $mediaItem['thumbnail_width'] = intval($mediaItem['thumbnail_width']);
                        $mediaItem['thumbnail_height'] = intval($mediaItem['thumbnail_height']);

                        $mediaPack[$tweetId][] = $mediaItem;
                    }
                }

                if (count($quotedTweetsIds)) {
                    $quotedTweets = self::getTweetsByIds($quotedTweetsIds);

                    $quotedTweetsData = [];
                    foreach ($quotedTweets as $quotedTweet) {
                        $tweetId = $quotedTweet['id'];

                        $quotedTweetsData[$tweetId] = $quotedTweet;
                    }
                }

                // Пользователи
                if (count($tweopleIds)) {
                    $tweople = \DB\Twitter\Tweople::where_in('id', $tweopleIds)->find_array();

                    $tweoplePack = [];
                    foreach ($tweople as $tweopleItem) {
                        $tweopleId = $tweopleItem['id'];

                        $tweoplePack[$tweopleId] = $tweopleItem;
                    }
                }

                foreach ($tweets as $i => &$tweet) {
                    $tweet['media'] = $tweet['user'] = null;

                    if (isset($tweoplePack[$tweet['tweople_id']])) {
                        $tweet['user'] = $tweoplePack[$tweet['tweople_id']];
                    }

                    if ($tweet['quoted_status_id'] > 0 && isset($quotedTweetsData[$tweet['quoted_status_id']])) {
                        $tweet['quoted_tweet'] = $quotedTweetsData[$tweet['quoted_status_id']];
                    }

                    if (isset($mediaPack[$tweet['id']])) {
                        $tweet['media'] = $mediaPack[$tweet['id']];

                        // Фильтрация по прикреплениям
                        if (isset($data['filter']) && $data['filter'] == self::FILTER_VIDEO) {
                            $isVideo = false;
                            foreach ($tweet['media'] as $mediaItem) {
                                if ($mediaItem['type'] == 2) {
                                    $isVideo = true;
                                }
                            }

                            if (!$isVideo) {
                                unset($tweets[$i]);
                                continue;
                            }
                        }
                    }

                    $tweet = self::outputModify($tweet);
                }
            }

            mc::set('tweetsPack' . $listId . $mcIntervalKey . $mcFilterKey, $tweets, 300);
        }

        $favoritesQueryIds = [];
        foreach ($tweets as $i => &$item) {
            // Excluding some accounts
            if (isset($data['exclude']) && is_array($data['exclude']) && count($data['exclude'])) {
                if (in_array($item['user']['id'], $data['exclude'])) {
                    unset($tweets[$i]);
                    continue;
                }
            }

            if ($idUser) {
                if (\Behavior::tweopleBlackListIsMember($item['user']['id'])) {
                    unset($tweets[$i]);
                    continue;
                }
            }

            $item['isFavorited'] = false;
            if ($idUser) {
                $favoritesQueryIds[] = intval($item['id']);
            }

            $item['listId'] = $listId;
        }

        if ($idUser && count($favoritesQueryIds)) {
            $favoritesIds = \Favorite::getFavoritesByType($idUser, \Favorite::TYPE_TWEET, $favoritesQueryIds);

            if (count($favoritesIds)) {
                unset($item);
                foreach ($tweets as &$item) {
                    if (in_array($item['id'], $favoritesIds)) {
                        $item['isFavorited'] = true;
                    }
                }
            }
        }

        $count = (isset($data['count'])) ? max(1, min(500, intval($data['count']))) : 50;
        $offset = (isset($data['offset'])) ? max(0, min(500, intval($data['offset']))) : 0;

        $tweets = array_slice($tweets, $offset, $count);

        return $tweets;
    }

    public static function getTweetsByIds($ids)
    {
        /////////////////////////////////////////////////////////////////////////////////////////////////////////
        // ВНИМАНИЕ! Здесь нет кэширования, эта функция надеется, что кэширование будет на принимающей стороне //
        /////////////////////////////////////////////////////////////////////////////////////////////////////////
        $tweets = \DB\Twitter\Tweet::where('deleted', 0)
            ->where_in('id', $ids)
            ->order_by_expr('Field(id, ' . implode(',', $ids) . ')')
            ->find_array();

        if (count($tweets)) {
            $tweetsIds = $tweopleIds = $quotedTweetsIds = $quotedTweetsData = [];
            foreach ($tweets as $i => $tweet) {
                if ($tweet['has_media'] == 1) {
                    $tweetsIds[] = $tweet['id'];
                }

                if ($tweet['quoted_status_id'] > 0) {
                    $quotedTweetsIds[] = $tweet['quoted_status_id'];
                }

                $tweopleIds[] = $tweet['tweople_id'];
            }

            // Медиаданные
            if (count($tweetsIds)) {
                $media = \DB\Twitter\Media::where_in('tweet_id', $tweetsIds)->find_array();

                $mediaPack = [];
                foreach ($media as $mediaItem) {
                    $tweetId = $mediaItem['tweet_id'];
                    unset($mediaItem['id'], $mediaItem['tweet_id']);
                    $mediaItem['thumbnail_width'] = intval($mediaItem['thumbnail_width']);
                    $mediaItem['thumbnail_height'] = intval($mediaItem['thumbnail_height']);

                    $mediaPack[$tweetId][] = $mediaItem;
                }
            }

            if (count($quotedTweetsIds)) {
                $quotedTweets = self::getTweetsByIds($quotedTweetsIds);

                $quotedTweetsData = [];
                foreach ($quotedTweets as $quotedTweet) {
                    $tweetId = $quotedTweet['id'];

                    $quotedTweetsData[$tweetId][] = $quotedTweet;
                }
            }

            // Пользователи
            if (count($tweopleIds)) {
                $tweople = \DB\Twitter\Tweople::where_in('id', $tweopleIds)->find_array();

                $tweoplePack = [];
                foreach ($tweople as $tweopleItem) {
                    $tweopleId = $tweopleItem['id'];

                    $tweoplePack[$tweopleId] = $tweopleItem;
                }
            }

            foreach ($tweets as &$tweet) {
                $tweet['media'] = $tweet['user'] = null;

                if (isset($tweoplePack[$tweet['tweople_id']])) {
                    $tweet['user'] = $tweoplePack[$tweet['tweople_id']];
                }

                if ($tweet['quoted_status_id'] > 0 && isset($quotedTweetsData[$tweet['quoted_status_id']])) {
                    $tweet['quoted_tweet'] = $quotedTweetsData[$tweet['quoted_status_id']];
                }

                if (isset($mediaPack[$tweet['id']])) {
                    $tweet['media'] = $mediaPack[$tweet['id']];
                }

                $tweet = self::outputModify($tweet);

                $tweet['isFavorited'] = true;
            }
        }

        return $tweets;
    }

    public static function getTweopleByIds($ids)
    {
        if (count($ids) == 0) {
            return [];
        }

        $items = mc::get('tweopleByIds' . md5(implode(',', $ids)));
        if (!mc::isResultOK()) {
            $items = \DB\Twitter\Tweople::where_in('id', $ids)
                ->order_by_expr('Field(id, ' . implode(',', $ids) . ')')
                ->find_array();

            foreach ($items as &$item) {
                $item['profile_image_url_bigger'] = str_replace('_normal', '_bigger', $item['profile_image_url']);
                $item['security_user_hash'] = md5($item['id'] . R::get('salt'));
            }

            mc::set('tweopleByIds' . md5(implode(',', $ids)), $items, 3600);
        }

        return $items;
    }

    /**
     * Обработка интервала
     *
     * @param  mixed $interval
     * @param  int   $defaultHours
     * @return array
     */
    private static function getInterval($interval, $defaultHours = 10)
    {
        if (!is_array($interval) && is_numeric($interval)) {
            $result = [time() - 3600 * intval($interval), time()];
        } elseif (is_array($interval) && count($interval) > 1) {
            if (!is_numeric($interval[0])) {
                $interval[0] = strtotime($interval[0]);
            }

            if (!is_numeric($interval[1])) {
                $interval[1] = strtotime($interval[1]);
            }

            $result = $interval;
        } else {
            $result = [time() - 3600 * $defaultHours, time()];
        }

        return $result;
    }

    /**
     * Обработка полученных из БД данных
     *
     * @param  array $tweet
     * @return array
     */
    public static function outputModify($tweet)
    {
        $parsedText = $tweet['parsed_text'];

        if (!empty($parsedText)) {
            $parsedText = preg_replace('/\[{2}([^\|]+)\|{2}(.+?)\|{2}([^\]]+)\]{2}/i', '<a href="$1" title="$2" class="tj-tweet-url" target="_blank">$3</a>', $parsedText);

            $tweet['text'] = $parsedText;
        } else {
            $tweet['parsed_text'] = $tweet['text'];
        }

        $tweet['user']['profile_image_url_bigger'] = str_replace('_normal', '_bigger', $tweet['user']['profile_image_url']);

        ksort($tweet['user']);

        unset($tweet['counters_last_update']);
        unset($tweet['tweople_id']);
        unset($tweet['deleted']);

        $tweet['id'] = (string) $tweet['id'];
        $tweet['created_at'] = intval($tweet['created_at']);
        $tweet['date'] = intval($tweet['date']);
        $tweet['retweet_count'] = intval($tweet['retweet_count']);
        $tweet['favorite_count'] = intval($tweet['favorite_count']);
        $tweet['user']['id'] = intval($tweet['user']['id']);
        $tweet['user']['created_at'] = intval($tweet['user']['created_at']);
        $tweet['user']['followers_count'] = intval($tweet['user']['followers_count']);
        $tweet['user']['friends_count'] = intval($tweet['user']['friends_count']);
        $tweet['user']['statuses_count'] = intval($tweet['user']['statuses_count']);
        if (!empty($tweet['media'])) {
            foreach ($tweet['media'] as &$media) {
                $media['type'] = intval($media['type']);
                $media['ratio'] = floatval($media['ratio']);
                unset($media['service']);
            }
        }
        $tweet['has_media'] = (bool) $tweet['has_media'];

        return $tweet;
    }

    /**
     * Блокировка аккаунта от отображения в списке
     *
     * @param  int     $id
     * @param  int     $listId
     * @return boolean
     */
    public static function banAccountFromList($id, $listId)
    {
        \DB\Twitter\TweopleStopList::raw_execute("INSERT INTO twitter_tweople_stoplists (tweople_id, list_id)
                    VALUES (:tweople_id, :list_id)
                    ON DUPLICATE KEY UPDATE tweople_id = VALUES(tweople_id), list_id = VALUES(list_id)", ['tweople_id' => $id, 'list_id' => $listId]);

        mc::delete('tweetsPack' . $listId . '7ru');

        return true;
    }

    /**
     * Удаление твита из всех категорий
     *
     * @param  int     $id
     * @return boolean
     */
    public static function deleteTweetFromTop($id)
    {
        $object = \DB\Twitter\Tweet::find_one($id);
        if ($object) {
            $object->deleted = 1;
            $object->save();

            return true;
        }
    }
}

class TweetPackException extends \Exception
{
}
