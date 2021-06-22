<?php

namespace exula;

class PlexApi extends \jc21\PlexApi {

    public function getPlaylists()
    {
        return $this->call('/playlists');
    }

    public function getPlaylistPath($name)
    {

        $playlists = $this->getPlaylists();

        foreach($playlists['Playlist'] as $playlist)
        {
            if($playlist['title'] === $name)
            {
                return $playlist['key'];
            }
        }
        return false;
    }

    public function getPlaylist($name) {

        $results = $this->call($this->getPlaylistPath($name));

        $items = $results['Video'];

        $files = [];

        foreach($items as $item)
        {
            $parts = [];
          if(!isset($item['Media']['Part'])) {
              foreach($item['Media'] as $media) {
                  $parts[] = $media['Part'];
              }
          } else {
              $parts[] = $item['Media']['Part'];
          }

            foreach($parts as $part) {
                $info = pathinfo($part['file']);

                $download = $part['key'];
                $directory = $info['dirname'];
                $filename = $info['basename'];
                $size = $part['size'];

                $files[] = ['download' => $download, 'filename' => $filename, 'directory' => $directory, 'size' => $size];
            }

        }

        return $files;

    }

    public function downloadFile($download, $fileHandle)
    {

        $this->call($download."?download=1", [], self::GET, false, $fileHandle);

    }

    protected function call($path, $params = [], $method = self::GET, $isLoginCall = false, $download = false)
    {
        if (!$this->token && !$isLoginCall) {
            $this->call('https://plex.tv/users/sign_in.xml', [], self::POST, true);
        }

        if ($isLoginCall) {
            $fullUrl = $path;
        } else {
            $fullUrl = $this->ssl ? 'https://' : 'http://';
            $fullUrl .= $this->host . ':' . $this->port . $path;
            if ($params && count($params)) {
                $fullUrl .= '?' . http_build_query($params);
            }
        }

        // Setup curl array
        $curlOptArray = [
            CURLOPT_URL            => $fullUrl,
            CURLOPT_AUTOREFERER    => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $this->timeout,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_FAILONERROR    => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER     => [
                'X-Plex-Client-Identifier: ' . $this->clientIdentifier,
                'X-Plex-Product: ' . $this->productName,
                'X-Plex-Version: 1.0',
                'X-Plex-Device: ' . $this->device,
                'X-Plex-Device-Name: ' . $this->deviceName,
                'X-Plex-Platform: Linux',
                'X-Plex-Platform-Version: 1.0',
                'X-Plex-Provides: controller',
                'X-Plex-Username: ' . $this->username,
            ],
        ];

        if ($isLoginCall) {
            $curlOptArray[CURLOPT_HTTPAUTH] = CURLAUTH_ANY;
            $curlOptArray[CURLOPT_USERPWD]  = $this->username . ':' . $this->password;
        } else {
            $curlOptArray[CURLOPT_HTTPHEADER][] = 'X-Plex-Token: ' . $this->token;
        }

        if ($method == self::POST) {
            $curlOptArray[CURLOPT_POST] = true;
        } else if ($method != self::GET) {
            $curlOptArray[CURLOPT_CUSTOMREQUEST] = $method;
        }

        // Reset vars
        $this->lastCallStats = null;

        // Send
        $resource = curl_init();
        curl_setopt_array($resource, $curlOptArray);

        // Download
        if($download !== false)
        {
            curl_setopt($resource, CURLOPT_FILE, $download);
        }

        // Send!
        $response = curl_exec($resource);

        // Stats
        $this->lastCallStats = curl_getinfo($resource);

        // Errors and redirect failures
        if (!$response) {
            $response        = false;
            error_log(curl_errno($resource) . ': ' . curl_error($resource));
        } else {
            if($download === false) {
                $response = self::xml2array($response);
            }

            if ($isLoginCall) {
                if ($this->lastCallStats['http_code'] != 201) {
                    throw new \Exception('Invalid status code in authentication response from Plex.tv, expected 201 but got ' . $this->lastCallStats['http_code']);
                }

                $this->token = $response['authentication-token'];
            }
        }

        curl_close($resource);
        return $response;
    }

}