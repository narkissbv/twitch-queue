<?php
  header("Expires: on, 01 Jan 1970 00:00:00 GMT");
  header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
  header("Cache-Control: no-store, no-cache, must-revalidate");
  header("Cache-Control: post-check=0, pre-check=0", false);
  header("Pragma: no-cache");
  $http_origin = $_SERVER['HTTP_ORIGIN'];
  header("Access-Control-Allow-Origin: $http_origin");
  include_once('db_connect.php');
  header('Content-Type: application/json');

  // Validate query params
  if (!isset($_GET['username']) ||
      !isset($_GET['channelId']) ||
      !isset($_GET['command'])) {
    http_response_code(404);
    $resp = array(
      'message' => 'Missing params',
      'data' => $_GET
    );
    die(json_encode($resp));
  }

  $username = mysqli_real_escape_string($link, $_GET['username']);
  $channel_id = mysqli_real_escape_string($link, $_GET['channelId']);
  $command = mysqli_real_escape_string($link, $_GET['command']);

  switch($command) {
    case 'join':
      # check whether channel is active
      $sql = "SELECT * FROM `queue_channel_config`
              WHERE channel_id='$channel_id'
              AND is_active=1";
      $config_rs = mysqli_query($link, $sql);
      if (mysqli_num_rows($config_rs) == 0) {
        $resp = array(
          'message' => 'Queue is now closed'
        );
        die(json_encode($resp));
      }

      # Check whether viewer is alreay in queue
      $sql = "SELECT * FROM `queue_manager`
              WHERE username='{$username}'
              AND channel_id='{$channel_id}'
              AND is_active=1";
      $user_rs = mysqli_query($link, $sql);
      if (mysqli_num_rows($user_rs) > 0) {
        $resp = array(
          'message' => "${username}, you are already in queue_manager. Please be patient"
        );
        die(json_encode($resp));
      }

      $sql = "SELECT max(priority) as maxp
              FROM `queue_manager`
              WHERE channel_id='{$channel_id}'
              AND is_active = 1";
      $max_rs = mysqli_query($link, $sql);
      $row = mysqli_fetch_assoc($max_rs);
      $max_priority = (int)$row['maxp'] + 1;

      $sql = "INSERT INTO `queue_manager` (username, priority, channel_id, is_active)
              VALUES('{$username}', {$max_priority}, '{$channel_id}', 1)";
      $result = mysqli_query($link, $sql);
      $resp = array(
        'message' => "{$username} added to queue"
      );
      die(json_encode($resp));
    case 'leave':
      # Check whether viewer is alreay in queue
      $sql = "SELECT * FROM `queue_manager`
              WHERE username='{$username}'
              AND channel_id='{$channel_id}'
              AND is_active=1";
      $user_rs = mysqli_query($link, $sql);
      if (mysqli_num_rows($user_rs) > 0) {
        # remove user from queue
        $sql = "UPDATE `queue_manager`
                SET is_active = 0
                WHERE username='{$username}'
                AND channel_id='{$channel_id}'";
        mysqli_query($link, $sql);
        $resp = array(
          'message' => "{$username}, you have been removed from queue"
        );
        die(json_encode($resp));
      }
      http_response_code(404);
      $resp = array(
        'message' => "Sorry, {$username}, couldn't find you in queue",
        'data' => $_GET
      );
      die(json_encode($resp));
      
      
    case 'up':
      $sql1 = "SELECT * FROM `queue_manager`
            WHERE username = '{$username}'
            AND channel_id = '$channel_id'
            AND is_active = 1";
      $user_rs = mysqli_query($link, $sql1);
      $user = mysqli_fetch_assoc($user_rs);
      $priority = (int)$user['priority'];

      $other_priority_sql = "SELECT max(priority)
                             AS `priority`
                             FROM `queue_manager`
                             WHERE priority < $priority
                             AND is_active = 1";
      $other_priority_rs = mysqli_query($link, $other_priority_sql);
      if (mysqli_num_rows($other_priority_rs) == 0) {
        http_response_code(400);
        $resp = array(
          'message' => "Error, $username is currently the first in queue",
          'data' => $_POST
        );
        die(json_encode($resp));
      }
      $other_priority_row = mysqli_fetch_assoc($other_priority_rs);
      $other_priority = (int)$other_priority_row['priority'];

      $sql2 = "SELECT * FROM `queue_manager`
               WHERE priority = {$other_priority}
               AND is_active = 1
               AND channel_id='$channel_id'";
      $other_rs = mysqli_query($link, $sql2);
      $other = mysqli_fetch_assoc($other_rs);
      
      $sql3 = "UPDATE `queue_manager`
            SET priority = {$other_priority}
            WHERE username = '{$username}'
            AND channel_id='$channel_id'
            AND is_active = 1";
      mysqli_query($link, $sql3);
      $other_name = $other['username'];
      $sql4 = "UPDATE `queue_manager`
            SET priority = {$priority}
            WHERE username = '{$other_name}'
            AND is_active = 1
            AND channel_id='$channel_id'";
      mysqli_query($link, $sql4);
      $resp = array(
        'message' => "$username has been promoted"
      );
      die(json_encode($resp));
    case 'down':
      $sql1 = "SELECT * FROM `queue_manager`
            WHERE username = '{$username}'
            AND channel_id = '$channel_id'
            AND is_active = 1";
      $user_rs = mysqli_query($link, $sql1);
      $user = mysqli_fetch_assoc($user_rs);
      $priority = (int)$user['priority'];

      $other_priority_sql = "SELECT min(priority)
                             AS `priority`
                             FROM `queue_manager`
                             WHERE priority > $priority
                             AND is_active = 1";
      $other_priority_rs = mysqli_query($link, $other_priority_sql);
      if (mysqli_num_rows($other_priority_rs) == 0) {
        http_response_code(400);
        $resp = array(
          'message' => "Error, $username is currently the last in queue",
          'data' => $_POST
        );
        die(json_encode($resp));
      }
      $other_priority_row = mysqli_fetch_assoc($other_priority_rs);
      $other_priority = (int)$other_priority_row['priority'];

      $sql2 = "SELECT * FROM `queue_manager`
               WHERE priority = {$other_priority}
               AND is_active = 1
               AND channel_id='$channel_id'";
      $other_rs = mysqli_query($link, $sql2);
      $other = mysqli_fetch_assoc($other_rs);
      
      $sql3 = "UPDATE `queue_manager`
            SET priority = {$other_priority}
            WHERE username = '{$username}'
            AND channel_id='$channel_id'
            AND is_active = 1";
      mysqli_query($link, $sql3);
      $other_name = $other['username'];
      $sql4 = "UPDATE `queue_manager`
            SET priority = {$priority}
            WHERE username = '{$other_name}'
            AND is_active = 1
            AND channel_id='$channel_id'";
      mysqli_query($link, $sql4);
      $resp = array(
        'message' => "$username has been demoted"
      );
      die(json_encode($resp));
    case 'list':
      $resp = array(
        'message' => 'Fetched list of viewers in queue',
        'data' => array()
      );
      $sql = "SELECT * FROM `queue_manager`
              WHERE channel_id = '$channel_id'
              AND is_active = 1
              ORDER BY `priority` ASC";
      $queue_rs = mysqli_query($link, $sql);
      while ($row = mysqli_fetch_assoc($queue_rs)) {
        $user = array(
          'username' => $row['username'],
          'priority' => $row['priority'],
          'channelId' => $row['channel_id'],
          'is_active' => (boolean)$row['is_active']
        );
        array_push($resp['data'], $user);
      }
      die(json_encode($resp));
    default:
      http_response_code(400);
      $resp = array(
        'message' => 'Missing params',
        'data' => $_GET
      );
      die(json_encode($resp));
  }
?>