# spotify_transliteration

Features of this repo:
- pull lyrics of songs locally and transliterate them!
- connect with your spotify account to pull the track that is playing live!
- currently transliterates korean and telugu since those are my go-to languages outside of english!

# Set Up

## In terminal:
1. clone this repo
1.5 create a virtual environment using venv -m venv venv
1.75 activate the virtual environment with source activate venv
2. run pip install -r requirements.txt

## In browser (I use chrome):

3. create a spotify developer app at https://developer.spotify.com/dashboard/applications (signin -> create app -> development mode)
4. copy paste your client id & your client secret to .env.php
5. open spotify's web player after signing in to your account
6. copy paste your sp_dc cookie into .env.php (on chrome: open up the inspect tool, go to application -> storage -> cookies -> copy paste your sp_dc cookie)

# Usage:

## In terminal:

7. run php -S 127.0.0.1:8000

## In browser:

8. open up http://127.0.0.1:8000/login.php on your browser from above
9. authorize this web app
10. go to http://127.0.0.1:8000/player_pink.html to find the lyric roller :)

## Features:
- Light mode / Dark mode button on botthom left hand corner
- original characters, korean romanized script and telugu romanized script menu on bottom right hand corner


If you have any questions reach out to me @sincerely.dimple on social media, make an issue on github or email me at dimple [no space] amithag @ g[mail] [dot] com

Also: I have a web app that doesn't require you to set anything up manually, but I need your spotify accounts to approve usage. If that's something you're interested in also let me know!


