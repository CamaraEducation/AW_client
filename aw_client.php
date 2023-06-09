<?php

define('SERVER', 'http://172.16.10.234/awdata/file.php');
define('AWDB', '/home/camara/.local/share/activitywatch/aw-server/peewee-sqlite.v2.db');

// retrieve the last recorded date
function getLastDate(){
    $db = new SQLite3('AWTimeLastExport.db');
    $res = $db->query("SELECT last_date from export_date where id=1");
    return $res->fetchArray()[0];
}

// function to export aw Files
function awData($file, $query){
    $date = getLastDate();

    // prepare the database
    $awdb = new SQLite3(AWDB);

    $result = $awdb->query($query);
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $data[] = $row;
    }
    $awdb->close();

    isset($data) ? $jsonData = json_encode($data) : $jsonData ='';
    
    file_put_contents($file, $jsonData, JSON_PRETTY_PRINT);
}

function updateLastDate(){
    $datetime = new DateTime("now", new DateTimeZone("UTC"));
    $last = $datetime->format('Y-m-d h:i:s.uP');
    $db = new SQLite3('AWTimeLastExport.db');
    $query = "UPDATE export_date SET last_date='$last' WHERE id=1";
    $db->exec($query);
    $db->close();
}

function init(){
    config();
    $lastDate = getLastDate();
    $devicename = file_get_contents('config');

    //identify which key belongs to which watcher
    $watcher_awdb = new SQLite3(AWDB);
    $watcher_query = "SELECT key FROM bucketmodel WHERE client = 'aw-watcher-afk'";
    $watcher_result = $watcher_awdb->query($watcher_query);
    while ($watcher_row = $watcher_result->fetchArray(SQLITE3_ASSOC)) {
        $watcher_key = $watcher_row['key'];
    }
    if ($watcher_key==1) {
        $ckey = 1;
        $akey = 2;
    } else {
        $ckey = 2;
        $akey = 1;
    }

    // computer usage data
    awData(
        "computer.json",
        "select '$devicename' as devicename, b.hostname, a.duration, strftime('%Y-%m-%d %H:%M:%S', a.timestamp) as datetimeadded, b.id, json_extract( a.datastr, '$.status') as status
        from eventmodel a
        join bucketmodel b on b.key = a.bucket_id
        where a.bucket_id = '$ckey' and a.timestamp > '$lastDate';"
    );

    // application usage data
    awData(
        "application.json",
        "select '$devicename' as devicename, b.hostname, a.duration, strftime('%Y-%m-%d %H:%M:%S', a.timestamp) as datetimeadded, b.id, 
		json_extract( a.datastr, '$.app') as app,
		json_extract( a.datastr, '$.title') as title
		from eventmodel a
		join bucketmodel b on b.key = a.bucket_id
		where a.bucket_id = '$akey' and a.timestamp > '$lastDate';"
    );

    // documets usage data
    // awData(
    //     "document.json",
    //     "select c.DeviceName ,a.Name, a.StartLocalTime, a.EndLocalTime, ROUND((JULIANDAY(a.EndLocalTime) - JULIANDAY(a.StartLocalTime)) * 86400) / 60 AS Duration from Ar_Activity a JOIN Ar_Timeline b on a.ReportId = b.ReportId JOIN Ar_Environment c on b.EnvironmentId = c.EnvironmentId where a.ReportId = 4 and StartLocalTime > '$lastDate'"
    // );

    streamData();
}

// data streaming function
function streamData(){

    $client_name = file_get_contents('config');
    $url = 'http://172.16.10.234/awdata/file.php';
    $file1 = new CURLFile('computer.json');
    $file2 = new CURLFile('application.json');
    // $file3 = new CURLFile('document.json');
    $data = array(
        'file1' => $file1,
        'file2' => $file2,
        // 'file3' => $file3,
        "client" => "$client_name",
    );
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $output = curl_exec($ch);
    curl_close($ch);
    $output = trim($output);


    // 2023-01-05 09:32:51
    if ($output == 'ok') {
        updateLastDate();
    }
}

function config(){

    $url = 'http://172.16.10.234/awdata/config.php';

    // file config.json does not exist send get request to url
    if (!file_exists('config')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);

        // if output is not empty write to file
        if ($output != '') {
            file_put_contents('config', $output);
        }
    }
    
}

init();

?>
