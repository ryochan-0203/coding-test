<?php
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

// エラーハンドリングを関数にまとめる
function handleError($message) {
    echo "エラー: $message\n";
    exit(1);
}

/**
 * 動画の長さを整形する関数
 *
 * @param string $duration 
 * @return string 動画の長さ
 */
function formatDuration(string $duration): string
{
    $duration = new DateInterval($duration);
    $hours = $duration->h;
    $minutes = $duration->i;
    $seconds = $duration->s;

    if ($hours > 0) {
        return sprintf('%d時間%02d分%02d秒', $hours, $minutes, $seconds);
    } elseif ($minutes > 0) {
        return sprintf('%d分%02d秒', $minutes, $seconds);
    } else {
        return sprintf('%d秒', $seconds);
    }
}

// APIキー
$apiKey = getenv('YOUTUBE_API_KEY');
if (!$apiKey) {
    handleError("APIキーが設定されていません。環境変数 'YOUTUBE_API_KEY' をセットしてください。");
}

// 引数の検証
if ($argc !== 2) {
    handleError("使用方法: php search.php <検索キーワード>");
}

$searchKeyword = $argv[1];

$client = new Google_Client();
$client->setApplicationName("YouTube Search App");
$client->setDeveloperKey($apiKey); 

$youtube = new Google_Service_YouTube($client);

try {
    $searchResponse = $youtube->search->listSearch('id,snippet', [
        'q' => $searchKeyword,
        'type' => 'video',
        'maxResults' => 20, 
    ]);

    // 検索結果が0件の場合の処理を追加
    if (empty($searchResponse['items'])) {
        echo "検索結果が見つかりませんでした。\n";
        exit(0); 
    }

    foreach ($searchResponse['items'] as $searchResult) {
        $videoId = $searchResult['id']['videoId'];
        $title = $searchResult['snippet']['title'];
        $channelTitle = $searchResult['snippet']['channelTitle'];

        // 動画の詳細情報を取得
        $videoResponse = $youtube->videos->listVideos('statistics,contentDetails', ['id' => $videoId]);
        $videoDetails = $videoResponse['items'][0];
        //視聴回数を整形
        $viewCount = number_format(intval($videoDetails['statistics']['viewCount'])); 
        //いいね数を整形
        $likeCount = isset($videoDetails['statistics']['likeCount']) 
            ? number_format(intval($videoDetails['statistics']['likeCount'])) 
            : 'N/A';
        //コメント数を整形
        $commentCount = isset($videoDetails['statistics']['commentCount']) 
            ? number_format(intval($videoDetails['statistics']['commentCount'])) 
            : 'N/A';
        
        // 動画の長さを整形する関数を呼び出す
        $formattedDuration = formatDuration($videoDetails['contentDetails']['duration']); 

        // 結果を整形して出力
        echo <<<EOT
------------------------------------
タイトル: $title
チャンネル名: $channelTitle
視聴回数: $viewCount 回
いいね数: $likeCount
コメント数: $commentCount
動画の長さ: $formattedDuration
URL: https://www.youtube.com/watch?v=$videoId
------------------------------------

EOT;
    }
} catch (Google_Service_Exception $e) {
    handleError(sprintf('YouTube API エラー: %s', $e->getMessage()));
} catch (Google_Exception $e) {
    handleError(sprintf('Google API クライアントエラー: %s', $e->getMessage()));
} catch (Exception $e) {
    handleError(sprintf('エラーが発生しました: %s', $e->getMessage()));
}