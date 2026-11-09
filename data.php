<?php

// Prevent timeouts and memory limits from stopping the script
set_time_limit(0);
ini_set('memory_limit', '256M'); // Low limit is fine now because we don't hold data in RAM

$filename = "data.csv";  
$number = 131350928; // First match ID to start from
$target_matches = 100000; // Number of successful matches to collect
$collected_matches = 0;

// --------------------------------------------------
// 1. Open CSV file once at the very beginning
// --------------------------------------------------
$fp = fopen($filename, "w");
if ($fp === false) {
    die("Cannot create CSV file.");
}

$headers_written = false;

echo "Starting safe streaming data collection for {$target_matches} matches..." . PHP_EOL;

for ($i = 0; $i < $target_matches; $i++) {
    $number--; // Increments to the next sequential number
    printf("Processing match ID: %d\n", $i);
    
    $matchid = (string)$number;  
 
    $url = "https://api.deadlock-api.com/v1/matches/" . $matchid . "/metadata";
    
    // --------------------------------------------------
    // 2. Fetch API data via cURL
    // --------------------------------------------------
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER   => ['Accept: application/json'],
        CURLOPT_TIMEOUT     => 10,
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($response === false || $http_code !== 200) {
        curl_close($ch);
        continue; // Skip missing matches safely
    }
    curl_close($ch);
    
    // --------------------------------------------------
    // 3. Decode JSON
    // --------------------------------------------------
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE || empty($data['match_info'])) {
        continue; 
    }
    
    $winning_team = $data['match_info']['winning_team'] ?? null;
    
    // --------------------------------------------------
    // 4. Extract players & write directly to disk (No RAM bloat)
    // --------------------------------------------------
    foreach ($data['match_info']['players'] ?? [] as $p) {
        $last = !empty($p['stats']) ? end($p['stats']) : [];
        $items = array_column($p['items'] ?? [], 'item_id');
    
        $player_row = [
            'match_id'             => $matchid,
            'win'                  => ($p['team'] ?? null) === $winning_team ? 1 : 0,
            'account_id'           => $p['account_id'] ?? null,
            'hero_id'              => $p['hero_id'] ?? null,
            'mvp_rank'             => $p['mvp_rank'] ?? null,
            'player_match_outcome' => $p['player_match_outcome'] ?? null,
            'team'                 => $p['team'] ?? null,
    
            'kills'                => $p['kills'] ?? null,
            'deaths'               => $p['deaths'] ?? null,
            'assists'              => $p['assists'] ?? null,
            'net_worth'            => $p['net_worth'] ?? null,
            'last_hits'            => $p['last_hits'] ?? null,
            'denies'               => $p['denies'] ?? null,
            'level'                => $p['level'] ?? null,
            'assigned_lane'        => $p['assigned_lane'] ?? null,
    
            'items'                => implode(';', $items),
    
            'player_damage'        => $last['player_damage'] ?? null,
            'neutral_damage'       => $last['neutral_damage'] ?? null,
            'objective_damage'     => $last['boss_damage'] ?? null,
            'player_healing'       => $last['player_healing'] ?? null,
            'self_healing'         => $last['self_healing'] ?? null,
            'player_damage_taken'  => $last['player_damage_taken'] ?? null,
            'damage_absorbed'      => $last['damage_absorbed'] ?? null,
            'shots_missed'         => $last['shots_missed'] ?? null,
            'heal_lost'            => $last['heal_lost'] ?? null,
    
            'neutral_kills'        => $last['neutral_kills'] ?? null,
            'creep_kills'          => $last['creep_kills'] ?? null,
            'bullet_kills'         => $last['bullet_kills'] ?? null,
            'melee_kills'          => $last['melee_kills'] ?? null,
            'ability_kills'        => $last['ability_kills'] ?? null,
            'headshot_kills'       => $last['headshot_kills'] ?? null,
    
            'player_barriering'    => $last['player_barriering'] ?? null,
            'teammate_healing'     => $last['teammate_healing'] ?? null,
            'teammate_barriering'  => $last['teammate_barriering'] ?? null,
            'self_damage'          => $last['self_damage'] ?? null,
        ];

        // Write headers automatically on the very first row
        if (!$headers_written) {
            fputcsv($fp, array_keys($player_row), ",", '"', "");
            $headers_written = true;
        }

        // Write each player row straight to the file on disk
        fputcsv($fp, $player_row, ",", '"', "");
    }

    $collected_matches++;
    
    // Print progress update every 100 matches
    if ($collected_matches % 100 === 0) {
        echo "Successfully streamed {$collected_matches} valid matches to disk..." . PHP_EOL;
    }
}

// --------------------------------------------------
// 5. Close file handle cleanly
// --------------------------------------------------
fclose($fp);

echo "Finished! Data successfully saved directly to: $filename" . PHP_EOL;