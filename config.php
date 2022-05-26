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

  // sanitize params
  if (isset($_GET['channelId'])) {
    $channel_id = $_GET['channelId'];
  }
  if (isset($_POST['channelId'])) {
    $channel_id = $_POST['channelId'];
  }
  if ($channel_id) {
    $channel_id = mysqli_real_escape_string($link, $channel_id);
  }
  $role = mysqli_real_escape_string($link, $_POST['role']);
  $is_active = mysqli_real_escape_string($link, $_POST['isActive']);
  $is_active = $is_active == "true" ? 1 : 0;

  // return channel_id's config
  if (isset($_GET['channelId'])) {
    $sql = "SELECT * FROM `queue_channel_config` WHERE channel_id='$channel_id'";
    $config_rs = mysqli_query($link, $sql);

    if (mysqli_num_rows($config_rs) == 0) {
      // channel not found
      http_response_code(404);
      $resp = array(
        'message' => "Channel '$channel_id' not found",
        "data" => $_GET
      );
      die(json_encode($resp));
    }

    $row = mysqli_fetch_assoc($config_rs);
    $resp = array(
      'message' => "Fetched queue config for channel $channel_id",
      'data' => array(
        'channelId' => $channel_id,
        'isActive' => $row['is_active'] == 1 ? true : false,
        'role' => $row['role']
      )
    );
    die(json_encode($resp));
  }

  // set a new config for channel
  // verify all params are in the request
  if (!isset($_POST['channelId']) ||
      !isset($_POST['role']) ||
      !isset($_POST['isActive'])) {
    http_response_code(400);
    $resp = array(
      'message' => 'Missing params',
      'data' => $_GET
    );
    die(json_encode($resp));
  }

  // store data in DB
  // Check whether channel is already in DB
  $sql = "SELECT * FROM `queue_channel_config` WHERE channel_id='$channel_id'";
  $config_rs = mysqli_query($link, $sql);
  if (mysqli_num_rows($config_rs) > 0) {
    // update existing record
    $sql = "UPDATE `queue_channel_config`
            SET is_active=$is_active,
            role='$role',
            modified=UNIX_TIMESTAMP(NOW())
            WHERE channel_id='$channel_id'";
    mysqli_query($link, $sql);
    $resp = array(
      'message' => "Channel $channel_id config updated",
      "data" => $_POST
    );
    die(json_encode($resp));
  }

  $sql = "INSERT INTO `queue_channel_config`
          (channel_id, is_active, role, modified)
          VALUES
          ('$channel_id', 1, '$role', UNIX_TIMESTAMP(NOW()))";
  mysqli_query($link, $sql);
  $resp = array(
    'message'=> "Channel $channel_id config added"
  );
  die(json_encode($resp));

?>