<?php
// .env.php
// Get this from Spotify Developer Dashboard
putenv('SPOTIFY_CLIENT_ID=...');
putenv('SPOTIFY_CLIENT_SECRET=...');
// This is the redirect URI you set in your Spotify Developer Dashboard, for local development you can use http://127.0.0.1:8000/callback.php
putenv('SPOTIFY_REDIRECT_URI=http://127.0.0.1:8000/callback.php');
// This is the SP_DC cookie value you get from the browser when you are logged in to Spotify when you check the cookies in the browser developer tools
putenv('SP_DC=...');