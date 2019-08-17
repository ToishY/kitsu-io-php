<?php 
require_once 'kitsuClass.php';

$kts = new KitsuHandler('oauth.json');

// Authenticated user id
$userId = $kts->uid;

// Get user library
$userLib = $kts->getUserLibrary(array('page[limit]'=>500,'page[offset]'=>0,'filter[userId]'=>$userId));

// Remap MAL ID to Kitsu ID
$seriesId = $kts->mappingInfoMAL(array('filter[external_site]'=>'myanimelist/anime','filter[external_id]'=>457))->data->id;

// Get info (Kitsu ID)
$seriesInfo = $kts->seriesInfo('anime', array('filter[id]'=>$seriesId));

// Get genre info (Kitsu ID)
$genreInfo = $kts->seriesGenres($seriesId, 'anime');
$show_genres = array_column($genreInfo->data, 'id');
var_dump($show_genres);

// Add new entry (Kitsu ID)
$newEntry = $kts->addEntry($seriesId ,array('status'=>'current','progress'=>13),'anime');

// Update entry (Kitsu ID)
$updEntry = $kts->updateEntry($seriesId ,array('status'=>'current','progress'=>15),'anime');

// Remove entry (Kitsu ID)
$remEntry = $kts->removeEntry(419, 'anime');

?>