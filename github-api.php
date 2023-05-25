<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);



// Import the GitHub API client library
require_once 'config.php';

// GitHub repository details
$owner = 'rorydale';
$repo = 'pointbreakradio';

// API endpoint for retrieving commits
$url = "https://api.github.com/repos/{$owner}/{$repo}/commits";

// Prepare the request headers
$headers = [
    'User-Agent: PHP',
    'Authorization: Bearer ' . GITHUB_ACCESS_TOKEN
];

// Initialize a cURL session
$curl = curl_init();

// Set the cURL options
curl_setopt_array($curl, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => 1,
    CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT'],
    CURLOPT_HTTPHEADER => $headers
]);

// Execute the request
$response = curl_exec($curl);

// Check for errors
if (curl_errno($curl)) {
    $error = curl_error($curl);
    // Handle the error
} else {
    // Process the response
    $apiResponse = json_decode($response, true);
    print json_encode($apiResponse);
    return;
    
    $commits = array();
    foreach ($apiResponse as $commit) {
      // Check if the "description" key exists in the commit array
      $commitString = $commit['commit']['message'];
      $commitParts = explode("\n\n", $commitString, 2); // Split the commit string into two parts
    
      $title = $commitParts[0]; // Assign the first part as the title
    
      // Validate the title as a date in the format "YYYY-mm-dd"
      $date = DateTime::createFromFormat('Y-m-d', $title);
      $isValidTitle = $date && $date->format('Y-m-d') === $title;
      // Make sure $title is a date to only list the commits that are shows!
      if ($isValidTitle) {
        
        if (isset($commitParts[1])) {
          // Split the second part of the commit string at the first instance of "-"
          $descriptionParts = explode('-', $commitParts[1], 2);
      
          // Capitalize the first letter of the second part
          $secondPart = trim($descriptionParts[1]); // Remove leading/trailing whitespace
          $capitalizedSecondPart = ucfirst($secondPart); // Capitalize the first letter

        }
      
        $commitData = array(
          'title' => $title,
          'description' => $capitalizedSecondPart
        );
      
        $commits[] = $commitData;

      }  
 
    }
  
    // Pass the extracted commit data to the Handlebars.js template
    print json_encode($commits);
}

// Close the cURL session
curl_close($curl);
