<?php
// Configuration
$token = 'abc123abc123abc123abc123';
$dropletId = '123123123';
$backupPrefix = 'auto-';

// Delete any snapshots older than $threshold
$threshold = 3600 * 72; // 72 hours (3 days)

/**
 * Class for communicating with the Digitalocean API
 */
class DigitalOceanAPI {
  private $url, $token;
  /**
   * Constructor. Checks and prepares the required dependencies.
   *
   * @param string $url
   * @param string $token
   */
  public function __construct($url, $token) {
    // Check dependencies
    if (!function_exists('curl_version')) {
      throw new Exception('PHP requires the cURL extension to communicate with the DigitalOcean API');
    }
    $this->url = $url;
    $this->token = $token;
  }
  /**
   * Does the communication part.
   *
   * @param string $resource
   * @param array $postdata
   * @return stdClass Response.
   */
  private function request($resource, $postdata = NULL, $customtype = NULL) {
    // Create cURL resource
    $ch = curl_init();
    // Set application options
    curl_setopt($ch, CURLOPT_URL, $this->url . $resource);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Authorization: Bearer ' . $this->token
    ));
    if ($postdata !== NULL) {
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postdata));
    }
    if ($customtype !== NULL) {
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $customtype);
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
    // Fire the request and clean up
    $response = curl_exec($ch);
    curl_close($ch);
    // Parse and return result
    return json_decode($response);
  }
  /**
   * Triggers the creation of a new snapshot
   *
   * @param string $id Volume to create snapshot of.
   */
  public function createDropletSnapshot($id, $name) {
    $this->request("droplets/{$id}/actions", array('type' => 'snapshot', 'name' => $name));
  }
  /**
   * Creates a list of all the avilable snapshots
   *
   * @param string $id Volume to retrieve snapshots for.
   * @param string $threshold If used, gets all snapshots that are OLDER than threshold.
   * @return array
   */
  public function getDropletSnapshots($id, $threshold = NULL, $filterprefix = '') {
    $response = $this->request("snapshots?resource_type=droplet");
    $snapshots = (empty($response) || empty($response->snapshots) ? array() : $response->snapshots);

    if($threshold) {
      $now = time();
      $list = array();

      foreach ($snapshots as $snapshot) {
        // Only use given volume name

        if ($snapshot->resource_id != $id) continue;

        if (!empty($filterprefix) && substr($snapshot->name, 0, strlen($filterprefix)) !== $filterprefix) {
          continue; // Invalid prefix, skipping…
        }
        
        // Get snapshots that exceed threshold
        if (($now - strtotime($snapshot->created_at)) > $threshold) {
          $list[] = $snapshot;
        }
      }

      return $list;

    }
    else {
      return $snapshots;
    }
  }
  /**
   * Triggers deletion of the specifed snapshot.
   *
   * @param string $id Snapshot to delete.
   */
  public function deleteSnapshot($id) {
    $this->request("snapshots/{$id}", NULL, 'DELETE');
  }
}

// Prepare api
$api = new DigitalOceanAPI('https://api.digitalocean.com/v2/', $token);

// Trigger the creation of a new snapshot
$api->createDropletSnapshot($dropletId, $backupPrefix . $dropletId . '-' . date('-Ymd-His'));

$snapshots = $api->getDropletSnapshots($dropletId, $threshold, $backupPrefix);
foreach ($snapshots as $snapshot) {
  $api->deleteSnapshot($snapshot->id);
}

