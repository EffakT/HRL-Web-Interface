---
title: API Reference

language_tabs:
- bash
- javascript
- php

includes:

search: true

toc_footers:
- <a href='http://github.com/mpociot/documentarian'>Documentation Powered by Documentarian</a>
---
<!-- START_INFO -->
# Info

Welcome to the generated API reference.
[Get Postman Collection](https://haloraceleaderboard.effakt.info/docs/collection.json)

<!-- END_INFO -->

#Maps


<!-- START_554b3719ed175060245101662cc39460 -->
## List Maps
Get a paginated list of all maps

<br><small style="padding: 1px 9px 2px;font-weight: bold;white-space: nowrap;color: #ffffff;-webkit-border-radius: 9px;-moz-border-radius: 9px;border-radius: 9px;background-color: #3a87ad;">Requires authentication</small>
> Example request:

```bash
curl -X GET \
    -G "https://haloraceleaderboard.effakt.info/api/maps" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "https://haloraceleaderboard.effakt.info/api/maps"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```

```php

$client = new \GuzzleHttp\Client();
$response = $client->get(
    'https://haloraceleaderboard.effakt.info/api/maps',
    [
        'headers' => [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ],
    ]
);
$body = $response->getBody();
print_r(json_decode((string) $body));
```


> Example response (200):

```json
{
    "data": [
        {
            "id": 1,
            "name": "bloodgulch",
            "label": "Bloodgulch"
        },
        {
            "id": 2,
            "name": "dangercanyon",
            "label": "Danger Canyon"
        },
        {
            "id": 3,
            "name": "timberland",
            "label": "Timberland"
        },
        {
            "id": 4,
            "name": "deathisland",
            "label": "Death Island"
        },
        {
            "id": 5,
            "name": "gephyrophobia",
            "label": "Gephyrophobia"
        },
        {
            "id": 6,
            "name": "icefields",
            "label": "Ice Fields"
        },
        {
            "id": 7,
            "name": "infinity",
            "label": "Infinity"
        },
        {
            "id": 8,
            "name": "sidewinder",
            "label": "Sidewinder"
        },
        {
            "id": 9,
            "name": "chillout",
            "label": "Chillout"
        },
        {
            "id": 10,
            "name": "bloodgulch",
            "label": "Bloodgulch - Any Order"
        }
    ],
    "links": {
        "first": "https:\/\/haloraceleaderboard.effakt.info\/api\/maps?page=1",
        "last": "https:\/\/haloraceleaderboard.effakt.info\/api\/maps?page=1",
        "prev": null,
        "next": null
    },
    "meta": {
        "current_page": 1,
        "from": 1,
        "last_page": 1,
        "path": "https:\/\/haloraceleaderboard.effakt.info\/api\/maps",
        "per_page": "10",
        "to": 10,
        "total": 10
    }
}
```

### HTTP Request
`GET api/maps`


<!-- END_554b3719ed175060245101662cc39460 -->

<!-- START_7a430468ab33019a63e03199d1078a2c -->
## Get Map
Get a map and a list of its lap times

<br><small style="padding: 1px 9px 2px;font-weight: bold;white-space: nowrap;color: #ffffff;-webkit-border-radius: 9px;-moz-border-radius: 9px;border-radius: 9px;background-color: #3a87ad;">Requires authentication</small>
> Example request:

```bash
curl -X GET \
    -G "https://haloraceleaderboard.effakt.info/api/maps/1" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "https://haloraceleaderboard.effakt.info/api/maps/1"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```

```php

$client = new \GuzzleHttp\Client();
$response = $client->get(
    'https://haloraceleaderboard.effakt.info/api/maps/1',
    [
        'headers' => [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ],
    ]
);
$body = $response->getBody();
print_r(json_decode((string) $body));
```


> Example response (200):

```json
{
    "data": {
        "id": 1,
        "name": "bloodgulch",
        "label": "Bloodgulch"
    },
    "laps": [
        {
            "id": 886,
            "time": "62.10",
            "date": "2018-07-05T00:00:00.000000Z",
            "player": {
                "id": 6,
                "name ": "HLN«ßÕX3R»"
            },
            "server": {
                "id": 4,
                "ip": "163.47.230.216",
                "port": "2302",
                "name": "Halo Race Leaderboard - Demo Server 04.04.20"
            }
        },
        {
            "id": 876,
            "time": "65.37",
            "date": "2018-03-01T00:00:00.000000Z",
            "player": {
                "id": 5,
                "name ": "©opyrite"
            },
            "server": {
                "id": 4,
                "ip": "163.47.230.216",
                "port": "2302",
                "name": "Halo Race Leaderboard - Demo Server 04.04.20"
            }
        }
    ]
}
```

### HTTP Request
`GET api/maps/{map}`


<!-- END_7a430468ab33019a63e03199d1078a2c -->

#Players


<!-- START_93bfac9632d7791d6c2cb79f153cf516 -->
## List Players
Get a paginated list of all players

<br><small style="padding: 1px 9px 2px;font-weight: bold;white-space: nowrap;color: #ffffff;-webkit-border-radius: 9px;-moz-border-radius: 9px;border-radius: 9px;background-color: #3a87ad;">Requires authentication</small>
> Example request:

```bash
curl -X GET \
    -G "https://haloraceleaderboard.effakt.info/api/players" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "https://haloraceleaderboard.effakt.info/api/players"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```

```php

$client = new \GuzzleHttp\Client();
$response = $client->get(
    'https://haloraceleaderboard.effakt.info/api/players',
    [
        'headers' => [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ],
    ]
);
$body = $response->getBody();
print_r(json_decode((string) $body));
```


> Example response (200):

```json
{
    "data": [
        {
            "id": 5,
            "name ": "©opyrite"
        },
        {
            "id": 6,
            "name ": "HLN«ßÕX3R»"
        },
        {
            "id": 7,
            "name ": "WarNeverDie"
        },
        {
            "id": 8,
            "name ": "CryForce"
        },
        {
            "id": 9,
            "name ": "destroyer"
        },
        {
            "id": 10,
            "name ": "GåþøFêîk¬£Q"
        },
        {
            "id": 11,
            "name ": "Pretty Girl"
        },
        {
            "id": 12,
            "name ": "Fooch"
        },
        {
            "id": 13,
            "name ": "Mr Hankey"
        },
        {
            "id": 14,
            "name ": "Malleus"
        }
    ],
    "links": {
        "first": "https:\/\/haloraceleaderboard.effakt.info\/api\/players?page=1",
        "last": "https:\/\/haloraceleaderboard.effakt.info\/api\/players?page=14",
        "prev": null,
        "next": "https:\/\/haloraceleaderboard.effakt.info\/api\/players?page=2"
    },
    "meta": {
        "current_page": 1,
        "from": 1,
        "last_page": 14,
        "path": "https:\/\/haloraceleaderboard.effakt.info\/api\/players",
        "per_page": "10",
        "to": 10,
        "total": 140
    }
}
```

### HTTP Request
`GET api/players`


<!-- END_93bfac9632d7791d6c2cb79f153cf516 -->

<!-- START_c59ee9cd29373e2b291359a420c59443 -->
## Get Player
Get a specific player and a list of their lap times

<br><small style="padding: 1px 9px 2px;font-weight: bold;white-space: nowrap;color: #ffffff;-webkit-border-radius: 9px;-moz-border-radius: 9px;border-radius: 9px;background-color: #3a87ad;">Requires authentication</small>
> Example request:

```bash
curl -X GET \
    -G "https://haloraceleaderboard.effakt.info/api/players/1" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "https://haloraceleaderboard.effakt.info/api/players/1"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```

```php

$client = new \GuzzleHttp\Client();
$response = $client->get(
    'https://haloraceleaderboard.effakt.info/api/players/1',
    [
        'headers' => [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ],
    ]
);
$body = $response->getBody();
print_r(json_decode((string) $body));
```


> Example response (200):

```json
{
    "data": {
        "id": 5,
        "name ": "©opyrite"
    },
    "laps": [
        {
            "id": 912,
            "time": "42.90",
            "date": "2018-04-21T00:00:00.000000Z",
            "map": {
                "id": 9,
                "name": "chillout",
                "label": "Chillout"
            },
            "server": {
                "id": 4,
                "ip": "163.47.230.216",
                "port": "2302",
                "name": "Halo Race Leaderboard - Demo Server 04.04.20"
            }
        },
        {
            "id": 875,
            "time": "47.27",
            "date": "2020-04-03T00:00:00.000000Z",
            "map": {
                "id": 3,
                "name": "timberland",
                "label": "Timberland"
            },
            "server": {
                "id": 4,
                "ip": "163.47.230.216",
                "port": "2302",
                "name": "Halo Race Leaderboard - Demo Server 04.04.20"
            }
        }
    ]
}
```

### HTTP Request
`GET api/players/{player}`


<!-- END_c59ee9cd29373e2b291359a420c59443 -->

#Servers


<!-- START_3867421074cac7359b4ccc82f8a25dac -->
## List Servers
Get a paginated list of all servers

<br><small style="padding: 1px 9px 2px;font-weight: bold;white-space: nowrap;color: #ffffff;-webkit-border-radius: 9px;-moz-border-radius: 9px;border-radius: 9px;background-color: #3a87ad;">Requires authentication</small>
> Example request:

```bash
curl -X GET \
    -G "https://haloraceleaderboard.effakt.info/api/servers" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "https://haloraceleaderboard.effakt.info/api/servers"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```

```php

$client = new \GuzzleHttp\Client();
$response = $client->get(
    'https://haloraceleaderboard.effakt.info/api/servers',
    [
        'headers' => [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ],
    ]
);
$body = $response->getBody();
print_r(json_decode((string) $body));
```


> Example response (200):

```json
{
    "data": [
        {
            "id": 4,
            "ip": "163.47.230.216",
            "port": "2302",
            "name": "Halo Race Leaderboard - Demo Server 04.04.20",
            "created_at": "2020-04-02T23:14:44.000000Z",
            "latest_lap": {
                "id": 1096,
                "time": "100.00",
                "date": "2020-04-11T00:00:00.000000Z",
                "map": {
                    "id": 1,
                    "name": "bloodgulch",
                    "label": "Bloodgulch"
                },
                "player": {
                    "id": 143,
                    "name ": "EffakT"
                }
            }
        }
    ],
    "links": {
        "first": "https:\/\/haloraceleaderboard.effakt.info\/api\/servers?page=1",
        "last": "https:\/\/haloraceleaderboard.effakt.info\/api\/servers?page=1",
        "prev": null,
        "next": null
    },
    "meta": {
        "current_page": 1,
        "from": 1,
        "last_page": 1,
        "path": "https:\/\/haloraceleaderboard.effakt.info\/api\/servers",
        "per_page": "10",
        "to": 1,
        "total": 1
    }
}
```

### HTTP Request
`GET api/servers`


<!-- END_3867421074cac7359b4ccc82f8a25dac -->

<!-- START_4c0c7788dbc14f2ab65edc6f5ebeded4 -->
## Get Server
Get a specific server and a list of its lap times

<br><small style="padding: 1px 9px 2px;font-weight: bold;white-space: nowrap;color: #ffffff;-webkit-border-radius: 9px;-moz-border-radius: 9px;border-radius: 9px;background-color: #3a87ad;">Requires authentication</small>
> Example request:

```bash
curl -X GET \
    -G "https://haloraceleaderboard.effakt.info/api/servers/1" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "https://haloraceleaderboard.effakt.info/api/servers/1"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```

```php

$client = new \GuzzleHttp\Client();
$response = $client->get(
    'https://haloraceleaderboard.effakt.info/api/servers/1',
    [
        'headers' => [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ],
    ]
);
$body = $response->getBody();
print_r(json_decode((string) $body));
```


> Example response (200):

```json
{
    "data": {
        "id": 4,
        "ip": "163.47.230.216",
        "port": "2302",
        "name": "Halo Race Leaderboard - Demo Server 04.04.20",
        "created_at": "2020-04-02T23:14:44.000000Z",
        "latest_lap": {
            "id": 1096,
            "time": "100.00",
            "date": "2020-04-11T00:00:00.000000Z",
            "map": {
                "id": 1,
                "name": "bloodgulch",
                "label": "Bloodgulch"
            },
            "player": {
                "id": 143,
                "name ": "EffakT"
            }
        }
    },
    "laps": [
        {
            "id": 9,
            "time": "38.87",
            "date": "2018-07-05T00:00:00.000000Z",
            "map": {
                "id": 9,
                "name": "chillout",
                "label": "Chillout"
            },
            "player": {
                "id": 6,
                "name ": "HLN«ßÕX3R»"
            }
        },
        {
            "id": 9,
            "time": "42.90",
            "date": "2018-04-21T00:00:00.000000Z",
            "map": {
                "id": 9,
                "name": "chillout",
                "label": "Chillout"
            },
            "player": {
                "id": 5,
                "name ": "©opyrite"
            }
        }
    ]
}
```

### HTTP Request
`GET api/servers/{server}`


<!-- END_4c0c7788dbc14f2ab65edc6f5ebeded4 -->


