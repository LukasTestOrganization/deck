
The REST API provides access for authenticated users to their data inside the Deck app. To get a better understanding of Decks data models and their relations, please have a look at the  [data structure](structure.md) documentation.

# Prequisited

- All requests require a `OCS-APIRequest` HTTP header to be set to `true` and a `Content-Type` of `application/json`.
- The API is located at https://nextcloud.local/index.php/apps/deck/api/v1.0
- All request parameters are required, unless otherwise specified

## Naming

- Board is the the project like grouping of tasks that can be shared to different users and groups

- Stack is the grouping of cards which is rendered in vertical columns in the UI

- Card is the representation of a single task

- Labels are defined on a board level and can be assigned to any number of cards

## Global responses

### 400 Bad request

In case the request is invalid, e.g. because a parameter is missing or an invalid value has been transmitted, a 400 error will be returned:

```json
{
  "status": 400,
  "message": "title must be provided"
}
```

### 403 Permission denied

In any case a user doesn't have access to a requested entity, a 403 error will be returned:

```json
{
    "status": 403,
    "message": "Permission denied"
}
```

## Formats

### Date

Datetime values in request data need to be provided in ISO-8601. Example: 2020-01-20T09:52:43+00:00

## Headers

### If-Modified-Since

Some index endpoints support limiting the result set to entries that have been changed since the given time.
The supported date formats are:

* IMF-fixdate:                 `Sun, 03 Aug 2019 10:34:12 GMT`
* (obsolete) RFC 850:          `Sunday, 03-Aug-19 10:34:12 GMT`
* (obsolete) ANSI C asctime(): `Sun Aug  3 10:34:12 2019`

It is highly recommended to only use the IMF-fixdate format. Note that according to [RFC2616](https://tools.ietf.org/html/rfc2616#section-3.3) all HTTP date/time stamps MUST be represented in Greenwich Mean Time (GMT), without exception.

Example curl request:

```bash
curl -u admin:admin -X GET \
    'http://localhost:8000/index.php/apps/deck/api/v1.0/boards/2/stacks' \
    -H "OCS-APIRequest: true" \
    -H "If-Modified-Since: Mon, 05 Nov 2018 09:28:00 GMT"
```

### ETag

An ETag header is returned in order to determine if further child elements have been updated for the following endpoints:

- Fetch all user board `GET /api/v1.0/boards`
- Fetch a single board `GET /api/v1.0/boards/{boardId}`
- Fetch all stacks of a board `GET /api/v1.0/boards/{boardId}/stacks`
- Fetch a single stacks of a board `GET /api/v1.0/boards/{boardId}/stacks/{stackId}`
- Fetch a single card of a board `GET /api/v1.0/boards/{boardId}/stacks/{stackId}/cards/{cardId}`
- Fetch attachments of a card `GET /api/v1.0/boards/{boardId}/stacks/{stackId}/cards/{cardId}/attachments`

If a `If-None-Match` header is provided and the requested element has not changed a `304` Not Modified response will be returned. 

Changes of child elements will propagate to their parents and also cause an update of the ETag which will be useful for determining if a sync is necessary on any client integration side. As an example, if a label is added to a card, the ETag of all related entities (the card, stack and board) will change.

If available the ETag will also be part of JSON response objects as shown below for a card:

```json
{
  "id": 81,
  "ETag": "bdb10fa2d2aeda092a2b6b469454dc90",
  "title": "Test card"
}
```

# Changelog

## 1.0.0

- The maximum length of the card title has been extended from 100 to 255 characters
- The API will now return a 400 Bad request response if the length limitation of a board, stack or card title is exceeded

# Endpoints

## Boards

### GET /boards - Get a list of boards

#### Headers

The board list endpoint supports setting an `If-Modified-Since` header to limit the results to entities that are changed after the provided time.

#### Request parameters

| Parameter | Type    | Description                  |
| --------- | ------- | ---------------------------- |
| details   | Bool    | **Optional** Enhance boards with details about labels, stacks and users |

#### Response

##### 200 Success

Returns an array of board items

```json
[
    {
        "title": "Board title",
        "owner": {
            "primaryKey": "admin",
            "uid": "admin",
            "displayname": "Administrator"
        },
        "color": "ff0000",
        "archived": false,
        "labels": [],
        "acl": [],
        "permissions": {
            "PERMISSION_READ": true,
            "PERMISSION_EDIT": true,
            "PERMISSION_MANAGE": true,
            "PERMISSION_SHARE": true
        },
        "users": [],
        "shared": 0,
        "deletedAt": 0,
        "id": 10,
        "lastModified": 1586269585,
        "settings": {
            "notify-due": "off",
            "calendar": true
        }
    }
]
```

### POST /boards - Create a new board

#### Request body

| Parameter | Type   | Description                                          |
| --------- | ------ | ---------------------------------------------------- |
| title     | String | The title of the new board, maximum length is limited to 100 characters |
| color     | String | The hexadecimal color of the new board (e.g. FF0000) |

```json
{
    "title": "Board title",
    "color": "ff0000"
}
```

#### Response

##### 200 Success

```json
{
    "title": "Board title",
    "owner": {
        "primaryKey": "admin",
        "uid": "admin",
        "displayname": "Administrator"
    },
    "color": "ff0000",
    "archived": false,
    "labels": [
        {
            "title": "Finished",
            "color": "31CC7C",
            "boardId": 10,
            "cardId": null,
            "id": 37
        },
        {
            "title": "To review",
            "color": "317CCC",
            "boardId": 10,
            "cardId": null,
            "id": 38
        },
        {
            "title": "Action needed",
            "color": "FF7A66",
            "boardId": 10,
            "cardId": null,
            "id": 39
        },
        {
            "title": "Later",
            "color": "F1DB50",
            "boardId": 10,
            "cardId": null,
            "id": 40
        }
    ],
    "acl": [],
    "permissions": {
        "PERMISSION_READ": true,
        "PERMISSION_EDIT": true,
        "PERMISSION_MANAGE": true,
        "PERMISSION_SHARE": true
    },
    "users": [],
    "deletedAt": 0,
    "id": 10,
    "lastModified": 1586269585
}
```

##### 403 Forbidden

A 403 response might be returned if the users ability to create new boards has been disabled by the administrator. For checking this before, see the `canCreateBoards` value in the [Nextcloud capabilties](./API-Nextcloud.md).

### GET /boards/{boardId} - Get board details

#### Request parameters

| Parameter | Type    | Description                  |
| --------- | ------- | ---------------------------- |
| boardId   | Integer | The id of the board to fetch |

#### Response

##### 200 Success

```json
{
    "title": "Board title",
    "owner": {
        "primaryKey": "admin",
        "uid": "admin",
        "displayname": "Administrator"
    },
    "color": "ff0000",
    "archived": false,
    "labels": [
        {
            "title": "Finished",
            "color": "31CC7C",
            "boardId": "10",
            "cardId": null,
            "id": 37
        },
        {
            "title": "To review",
            "color": "317CCC",
            "boardId": "10",
            "cardId": null,
            "id": 38
        },
        {
            "title": "Action needed",
            "color": "FF7A66",
            "boardId": "10",
            "cardId": null,
            "id": 39
        },
        {
            "title": "Later",
            "color": "F1DB50",
            "boardId": "10",
            "cardId": null,
            "id": 40
        }
    ],
    "acl": [],
    "permissions": {
        "PERMISSION_READ": true,
        "PERMISSION_EDIT": true,
        "PERMISSION_MANAGE": true,
        "PERMISSION_SHARE": true
    },
    "users": [
        {
            "primaryKey": "admin",
            "uid": "admin",
            "displayname": "Administrator"
        }
    ],
    "deletedAt": 0,
    "id": 10
}
```

### PUT /boards/{boardId} - Update board details

#### Request body

| Parameter | Type   | Description                                          |
| --------- | ------ | ---------------------------------------------------- |
| title     | String | The title of the board, maximum length is limited to 100 characters |
| color     | String | The hexadecimal color of the board (e.g. FF0000) |
| archived  | Bool   | Whether or not this board should be archived. |

```json
{
    "title": "Board title",
    "color": "ff0000",
    "archived": false
}
```

#### Response

##### 200 Success

### DELETE /boards/{boardId} - Delete a board

#### Request parameters

| Parameter | Type    | Description                  |
| --------- | ------- | ---------------------------- |
| boardId   | Integer | The id of the board to fetch |

#### Response

##### 200 Success

### POST /boards/{boardId}/undo_delete - Restore a deleted board

#### Request parameters

| Parameter | Type    | Description                  |
| --------- | ------- | ---------------------------- |
| boardId   | Integer | The id of the board to fetch |

#### Response

##### 200 Success

### POST /boards/{boardId}/acl - Add new acl rule

#### Request body

| Parameter | Type   | Description                                          |
| --------- | ------ | ---------------------------------------------------- |
| type      | Integer | Type of the participant                              |
| participant     | String | The uid of the participant |
| permissionEdit  | Bool   | Setting if the participant has edit permissions |
| permissionShare  | Bool   | Setting if the participant has sharing permissions |
| permissionManage  | Bool   | Setting if the participant has management permissions |

##### Supported participant types:
- 0 User
- 1 Group
- 7 Circle

#### Response

##### 200 Success

```json
[{
  "participant": {
    "primaryKey": "userid",
    "uid": "userid",
    "displayname": "User Name"
  },
  "type": 0,
  "boardId": 1,
  "permissionEdit": true,
  "permissionShare": false,
  "permissionManage": true,
  "owner": false,
  "id": 1
}]
```

### PUT /boards/{boardId}/acl/{aclId} - Update an acl rule

#### Request parameters

| Parameter | Type   | Description                                          |
| --------- | ------ | ---------------------------------------------------- |
| permissionEdit  | Bool   | Setting if the participant has edit permissions |
| permissionShare  | Bool   | Setting if the participant has sharing permissions |
| permissionManage  | Bool   | Setting if the participant has management permissions |

#### Response

##### 200 Success

### DELETE /boards/{boardId}/acl/{aclId} - Delete an acl rule

#### Response

##### 200 Success

## Stacks

### GET /boards/{boardId}/stacks - Get stacks

#### Headers

The board list endpoint supports setting an `If-Modified-Since` header to limit the results to entities that are changed after the provided time.


#### Request parameters

| Parameter | Type    | Description                  |
| --------- | ------- | ---------------------------- |
| boardId   | Integer | The id of the board to fetch |

#### Response

```json
[
  {
    "title": "ToDo",
    "boardId": 2,
    "deletedAt": 0,
    "lastModified": 1541426139,
    "cards": [...],
    "order": 999,
    "id": 4
  }
]
```

##### 200 Success

### GET /boards/{boardId}/stacks/archived - Get list of archived stacks

#### Request parameters

| Parameter | Type    | Description                  |
| --------- | ------- | ---------------------------- |
| boardId   | Integer | The id of the board to fetch |

#### Response

```json
[
  {
    "title": "ToDo",
    "boardId": 2,
    "deletedAt": 0,
    "lastModified": 1541426139,
    "cards": [...],
    "order": 999,
    "id": 4
  }
]
```

##### 200 Success

### GET /boards/{boardId}/stacks/{stackId} - Get stack details

#### Request parameters

| Parameter | Type    | Description                              |
| --------- | ------- | ---------------------------------------- |
| boardId   | Integer | The id of the board the stack belongs to |
| stackId   | Integer | The id of the stack                      |

#### Response

##### 200 Success

### POST /boards/{boardId}/stacks - Create a new stack

#### Request body

| Parameter | Type    | Description                                          |
| --------- | ------- | ---------------------------------------------------- |
| title     | String | The title of the new stack, maximum length is limited to 100 characters |
| order     | Integer | Order for sorting the stacks                         |

#### Request parameters

| Parameter | Type    | Description                  |
| --------- | ------- | ---------------------------- |
| boardId   | Integer | The id of the board to fetch |

#### Response

##### 200 Success

### PUT /boards/{boardId}/stacks/{stackId} - Update stack details

#### Request parameters

| Parameter | Type    | Description                              |
| --------- | ------- | ---------------------------------------- |
| boardId   | Integer | The id of the board the stack belongs to |
| stackId   | Integer | The id of the stack                      |

#### Request body

| Parameter | Type    | Description                                          |
| --------- | ------- | ---------------------------------------------------- |
| title     | String | The title of the stack, maximum length is limited to 100 characters |
| order     | Integer | Order for sorting the stacks                         |

#### Response

##### 200 Success

### DELETE /boards/{boardId}/stacks/{stackId} - Delete a stack

#### Request parameters

| Parameter | Type    | Description                              |
| --------- | ------- | ---------------------------------------- |
| boardId   | Integer | The id of the board the stack belongs to |
| stackId   | Integer | The id of the stack                      |

#### Response

##### 200 Success

## Cards

### GET /boards/{boardId}/stacks/{stackId}/cards/{cardId} - Get card details

#### Request parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| boardId   | Integer | The id of the board the card belongs to |
| stackId   | Integer | The id of the stack the card belongs to |
| cardId    | Integer | The id of the card                      |

#### Response

##### 200 Success

### POST /boards/{boardId}/stacks/{stackId}/cards - Create a new card

#### Request parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| boardId   | Integer | The id of the board the card belongs to |
| stackId   | Integer | The id of the stack the card belongs to |

#### Request body

| Parameter | Type    | Description                                          |
| --------- | ------- | ---------------------------------------------------- |
| title     | String | The title of the card, maximum length is limited to 255 characters |
| type      | String  | Type of the card (for later use) use 'plain' for now |
| order     | Integer | Order for sorting the stacks                         |
| description | String  | _(optional)_ The markdown description of the card  |
| duedate   | timestamp | _(optional)_ The duedate of the card or null       |

#### Response

```json
{  
   "title":"Test",
   "description":null,
   "stackId":6,
   "type":"plain",
   "lastModified":1541528026,
   "createdAt":1541528026,
   "labels":null,
   "assignedUsers":null,
   "attachments":null,
   "attachmentCount":null,
   "owner":"admin",
   "order":999,
   "archived":false,
   "duedate": "2019-12-24T19:29:30+00:00",
   "deletedAt":0,
   "commentsUnread":0,
   "id":10,
   "overdue":0
}
```

##### 200 Success

### PUT /boards/{boardId}/stacks/{stackId}/cards/{cardId} - Update card details

#### Request parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| boardId   | Integer | The id of the board the card belongs to |
| stackId   | Integer | The id of the stack the card belongs to |
| cardId    | Integer | The id of the card                      |

#### Request data

| Parameter   | Type      | Description                                          |
|-------------|-----------|------------------------------------------------------|
| title     | String | The title of the card, maximum length is limited to 255 characters |
| description | String    | The markdown description of the card                 |
| type        | String    | Type of the card (for later use) use 'plain' for now |
| order       | Integer   | Order for sorting the stacks                         |
| duedate     | timestamp | The ISO-8601 formatted duedate of the card or null   |


```
{  
   "title": "Test card",
   "description": "A card description",
   "type": "plain",
   "order": 999,
   "duedate": "2019-12-24T19:29:30+00:00",
}
```

#### Response

##### 200 Success

### DELETE /boards/{boardId}/stacks/{stackId}/cards/{cardId} - Delete a card

#### Request parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| boardId   | Integer | The id of the board the card belongs to |
| stackId   | Integer | The id of the stack the card belongs to |
| cardId    | Integer | The id of the card                      |

#### Response

##### 200 Success

### PUT /boards/{boardId}/stacks/{stackId}/cards/{cardId}/assignLabel - Assign a label to a card

#### Request parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| boardId   | Integer | The id of the board the card belongs to |
| stackId   | Integer | The id of the stack the card belongs to |
| cardId    | Integer | The id of the card                      |

#### Request data

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| labelId   | Integer | The label id to assign to the card      |
#### Response

##### 200 Success

### PUT /boards/{boardId}/stacks/{stackId}/cards/{cardId}/removeLabel - Remove a label to a card

#### Request parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| boardId   | Integer | The id of the board the card belongs to |
| stackId   | Integer | The id of the stack the card belongs to |
| cardId    | Integer | The id of the card                      |

#### Request data

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| labelId   | Integer | The label id to remove to the card      |

#### Response

##### 200 Success

### PUT /boards/{boardId}/stacks/{stackId}/cards/{cardId}/assignUser - Assign a user to a card

#### Request parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| boardId   | Integer | The id of the board the card belongs to |
| stackId   | Integer | The id of the stack the card belongs to |
| cardId    | Integer | The id of the card                      |

#### Request data

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| userId    | String  | The user id to assign to the card       |

#### Response

##### 200 Success

```json
{
  "id": 3,
  "participant": {
    "primaryKey": "admin",
    "uid": "admin",
    "displayname": "admin"
  },
  "cardId": 1
}
```

##### 400 Bad request

```json
{
  "status": 400,
  "message": "The user is already assigned to the card"
}
```

The request can fail with a bad request response for the following reasons:
- Missing or wrongly formatted request parameters
- The user is already assigned to the card
- The user is not part of the board


### PUT /boards/{boardId}/stacks/{stackId}/cards/{cardId}/unassignUser - Unassign a user from a card

#### Request parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| boardId   | Integer | The id of the board the card belongs to |
| stackId   | Integer | The id of the stack the card belongs to |
| cardId    | Integer | The id of the card                      |

#### Request data

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| userId    | String  | The user id to unassign from the card   |

#### Response

##### 200 Success

### PUT /boards/{boardId}/stacks/{stackId}/cards/{cardId}/reorder - Change the sorting order of a card

#### Request parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| boardId   | Integer | The id of the board the card belongs to |
| stackId   | Integer | The id of the stack the card belongs to |
| cardId    | Integer | The id of the card                      |

#### Request data

| Parameter | Type    | Description                                                 |
| --------- | ------- | ----------------------------------------------------------- |
| order     | Integer | The position in the stack where the card should be moved to |
| stackId   | Integer | The id of the stack where the card should be moved to       |


#### Response

##### 200 Success

## Labels

### GET /boards/{boardId}/labels/{labelId} - Get label details

#### Request parameters

| Parameter | Type    | Description                              |
| --------- | ------- | ---------------------------------------- |
| boardId   | Integer | The id of the board the label belongs to |
| labelId   | Integer | The id of the label                      |

#### Response

##### 200 Success

```json
{
  "title": "Abgeschlossen",
  "color": "31CC7C",
  "boardId": "2",
  "cardId": null,
  "id": 5
}
```

### POST /boards/{boardId}/labels - Create a new label

#### Request parameters

| Parameter | Type    | Description                              |
| --------- | ------- | ---------------------------------------- |
| boardId   | Integer | The id of the board the label belongs to |

#### Request data

```json
{
  "title": "Finished",
  "color": "31CC7C"
}
```

#### Response

##### 200 Success

### PUT /boards/{boardId}/labels/{labelId} - Update label details

#### Request parameters

| Parameter | Type    | Description                              |
| --------- | ------- | ---------------------------------------- |
| boardId   | Integer | The id of the board the label belongs to |
| labelId   | Integer | The id of the label                      |


#### Request data

```json
{
  "title": "Finished",
  "color": "31CC7C"
}
```

#### Response

##### 200 Success

### DELETE /boards/{boardId}/labels/{labelId} - Delete a label

#### Request parameters

| Parameter | Type    | Description                              |
| --------- | ------- | ---------------------------------------- |
| boardId   | Integer | The id of the board the label belongs to |
| labelId   | Integer | The id of the label                      |

#### Response

##### 200 Success

## Attachments

### GET /boards/{boardId}/stacks/{stackId}/cards/{cardId}/attachments - Get a list of attachments

#### Request parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| boardId   | Integer | The id of the board the card belongs to |
| stackId   | Integer | The id of the stack the card belongs to |
| cardId    | Integer | The id of the card                      |

#### Response

##### 200 Success

```json
[
  {
    "cardId": 5,
    "type": "deck_file",
    "data": "6DADC2C69F4.eml",
    "lastModified": 1541529048,
    "createdAt": 1541529048,
    "createdBy": "admin",
    "deletedAt": 0,
    "extendedData": {
      "filesize": 922258,
      "mimetype": "application/octet-stream",
      "info": {
        "dirname": ".",
        "basename": "6DADC2C69F4.eml",
        "extension": "eml",
        "filename": "6DADC2C69F4"
      }
    },
    "id": 6
  }
]

```

### GET /boards/{boardId}/stacks/{stackId}/cards/{cardId}/attachments/{attachmentId} - Get the attachment file

#### Request parameters

| Parameter    | Type    | Description                                   |
| ------------ | ------- | --------------------------------------------- |
| boardId      | Integer | The id of the board the attachment belongs to |
| stackId      | Integer | The id of the stack the attachment belongs to |
| cardId       | Integer | The id of the card the attachment belongs to  |
| attachmentId | Integer | The id of the attachment                      |

#### Response

##### 200 Success

### POST /boards/{boardId}/stacks/{stackId}/cards/{cardId}/attachments - Upload an attachment

#### Request parameters

| Parameter | Type    | Description                                   |
| --------- | ------- | --------------------------------------------- |
| boardId   | Integer | The id of the board the attachment belongs to |
| stackId   | Integer | The id of the stack the attachment belongs to |
| cardId    | Integer | The id of the card the attachment belongs to  |

#### Request data

| Parameter | Type    | Description                                   |
| --------- | ------- | --------------------------------------------- |
| type      | String  | The type of the attachement                   |
| file      | Binary  | File data to add as an attachment             |

For now only `deck_file` is supported as an attachment type.

#### Response

##### 200 Success

### PUT /boards/{boardId}/stacks/{stackId}/cards/{cardId}/attachments/{attachmentId} - Update an attachment

#### Request parameters

| Parameter    | Type    | Description                                   |
| ------------ | ------- | --------------------------------------------- |
| boardId      | Integer | The id of the board the attachment belongs to |
| stackId      | Integer | The id of the stack the attachment belongs to |
| cardId       | Integer | The id of the card the attachment belongs to  |
| attachmentId | Integer | The id of the attachment                      |

#### Request data

| Parameter | Type    | Description                                   |
| --------- | ------- | --------------------------------------------- |
| type      | String  | The type of the attachement                   |
| file      | Binary  | File data to add as an attachment             |

For now only `deck_file` is supported as an attachment type.

#### Response

##### 200 Success

### DELETE /boards/{boardId}/stacks/{stackId}/cards/{cardId}/attachments/{attachmentId} - Delete an attachment

#### Request parameters

| Parameter    | Type    | Description                                   |
| ------------ | ------- | --------------------------------------------- |
| boardId      | Integer | The id of the board the attachment belongs to |
| stackId      | Integer | The id of the stack the attachment belongs to |
| cardId       | Integer | The id of the card the attachment belongs to  |
| attachmentId | Integer | The id of the attachment                      |

#### Response

##### 200 Success

### PUT /boards/{boardId}/stacks/{stackId}/cards/{cardId}/attachments/{attachmentId}/restore - Resore a deleted attachment

#### Request parameters

| Parameter    | Type    | Description                                   |
| ------------ | ------- | --------------------------------------------- |
| boardId      | Integer | The id of the board the attachment belongs to |
| stackId      | Integer | The id of the stack the attachment belongs to |
| cardId       | Integer | The id of the card the attachment belongs to  |
| attachmentId | Integer | The id of the attachment                      |

#### Response

##### 200 Success

# OCS API

The following endpoints are available through the Nextcloud OCS endpoint, which is available at `/ocs/v2.php/apps/deck/api/v1.0/`. 
This has the benefit that both the web UI as well as external integrations can use the same API.

## Config

Deck stores user and app configuration values globally and per board. The GET endpoint allows to fetch the current global configuration while board settings will be exposed through the board element on the regular API endpoints. 

### GET /api/v1.0/config - Fetch app configuration values

#### Response

| Config key | Description |
| --- | --- |
| calendar | Determines if the calendar/tasks integration through the CalDAV backend is enabled for the user (boolean) | 
| groupLimit | Determines if creating new boards is limited to certain groups of the instance. The resulting output is an array of group objects with the id and the displayname (Admin only)|  

```
{
  "ocs": {
    "meta": {
      "status": "ok",
      "statuscode": 200,
      "message": "OK"
    },
    "data": {
      "calendar": true,
      "groupLimit": [
        {
          "id": "admin",
          "displayname": "admin"
        }
      ]
    }
  }
}

```

### POST /api/v1.0/config/{id}/{key} - Set a config value


#### Request parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| id    | Integer | The id of the board                      |
| key     | String | The config key to set, prefixed with `board:{boardId}:` for board specific settings |
| value    | String | The value that should be stored for the config key |

##### Board configuration

| Key | Value |
| --- | ----- |
| notify-due | `off`, `assigned` or `all` |
| calendar | Boolean |
 
#### Example request

```
curl -X POST 'https://admin:admin@nextcloud.local/ocs/v2.php/apps/deck/api/v1.0/config/calendar' -H 'Accept: application/json' -H "Content-Type: application/json" -H 'OCS-APIRequest: true' --data-raw '{"value":false}'

{
  "ocs": {
    "meta": {
      "status": "ok",
      "statuscode": 200,
      "message": "OK"
    },
    "data": false
  }
}

```

## Comments

### GET /cards/{cardId}/comments - List comments

#### Request parameters

string $cardId, int $limit = 20, int $offset = 0

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| cardId    | Integer | The id of the card                      |
| limit     | Integer | The maximum number of comments that should be returned, defaults to 20 |
| offset    | Integer | The start offset used for pagination, defaults to 0 |

```
curl 'https://admin:admin@nextcloud/ocs/v2.php/apps/deck/api/v1.0/cards/12/comments' \
    -H 'Accept: application/json' -H 'OCS-APIRequest: true'
```

#### Response

A list of comments will be provided under the `ocs.data` key. If no or no more comments are available the list will be empty.

##### 200 Success

```
{
  "ocs": {
    "meta": {
      "status": "ok",
      "statuscode": 200,
      "message": "OK"
    },
    "data": [
      {
        "id": 175,
        "objectId": 12,
        "message": "This is a comment with a mention to  @alice",
        "actorId": "admin",
        "actorType": "users",
        "actorDisplayName": "Administrator",
        "creationDateTime": "2020-03-10T10:23:07+00:00",
        "mentions": [
          {
            "mentionId": "alice",
            "mentionType": "user",
            "mentionDisplayName": "alice"
          }
        ]
      }
    ]
  }
}
```

In case a comment is marked as a reply to another comment object, the parent comment will be added as `replyTo` entry to the response. Only the next parent node is added, nested replies are not exposed directly. 

```json
[
  {
    "id": 175,
    "objectId": 12,
    "message": "This is a comment with a mention to  @alice",
    "actorId": "admin",
    "actorType": "users",
    "actorDisplayName": "Administrator",
    "creationDateTime": "2020-03-10T10:23:07+00:00",
    "mentions": [
      {
        "mentionId": "alice",
        "mentionType": "user",
        "mentionDisplayName": "alice"
      }
    ],
    "replyTo": {
     "id": 175,
     "objectId": 12,
     "message": "This is a comment with a mention to  @alice",
     "actorId": "admin",
     "actorType": "users",
     "actorDisplayName": "Administrator",
     "creationDateTime": "2020-03-10T10:23:07+00:00",
     "mentions": [
       {
         "mentionId": "alice",
         "mentionType": "user",
         "mentionDisplayName": "alice"
       }
     ]
   }
  }
]
```


### POST /cards/{cardId}/comments - Create a new comment

#### Request parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| cardId    | Integer | The id of the card                      |
| message     | String | The message of the comment, maximum length is limited to 1000 characters |
| parentId    | Integer | _(optional)_ The start offset used for pagination, defaults to null |

Mentions will be parsed by the server. The server will return a list of mentions in the response to this request as shown below.

```
curl -X POST 'https://admin:admin@nextcloud/ocs/v2.php/apps/deck/api/v1.0/cards/12/comments' \
    -H 'Accept: application/json' -H 'OCS-APIRequest: true'
    -H 'Content-Type: application/json;charset=utf-8'
    --data '{"message":"My message to @bob","parentId":null}'
```

#### Response

A list of comments will be provided under the `ocs.data` key. If no or no more comments are available the list will be empty.

##### 200 Success

```
{
  "ocs": {
    "meta": {
      "status": "ok",
      "statuscode": 200,
      "message": "OK"
    },
    "data": {
      "id": "177",
      "objectId": "13",
      "message": "My message to @bob",
      "actorId": "admin",
      "actorType": "users",
      "actorDisplayName": "Administrator",
      "creationDateTime": "2020-03-10T10:30:17+00:00",
      "mentions": [
        {
          "mentionId": "bob",
          "mentionType": "user",
          "mentionDisplayName": "bob"
        }
      ]
    }
  }
}
```

##### 400 Bad request

A bad request response is returned if invalid input values are provided. The response message will contain details about which part was not valid.

##### 404 Not found

A not found response might be returned if:
- The card for the given cardId could not be found
- The parent comment could not be found


### PUT /cards/{cardId}/comments/{commentId} - Update a comment

#### Request parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| cardId    | Integer | The id of the card                      |
| commentId    | Integer | The id of the comment                      |
| message     | String | The message of the comment, maximum length is limited to 1000 characters |

Mentions will be parsed by the server. The server will return a list of mentions in the response to this request as shown below.

Updating comments is limited to the current user being the same as the comment author specified in the `actorId` of the comment.

```
curl -X POST 'https://admin:admin@nextcloud/ocs/v2.php/apps/deck/api/v1.0/cards/12/comments' \
    -H 'Accept: application/json' -H 'OCS-APIRequest: true'
    -H 'Content-Type: application/json;charset=utf-8'
    --data '{"message":"My message"}'
```

#### Response

A list of comments will be provided under the `ocs.data` key. If no or no more comments are available the list will be empty.

##### 200 Success

```
{
  "ocs": {
    "meta": {
      "status": "ok",
      "statuscode": 200,
      "message": "OK"
    },
    "data": {
      "id": "177",
      "objectId": "13",
      "message": "My message",
      "actorId": "admin",
      "actorType": "users",
      "actorDisplayName": "Administrator",
      "creationDateTime": "2020-03-10T10:30:17+00:00",
      "mentions": []
    }
  }
}
```

##### 400 Bad request

A bad request response is returned if invalid input values are provided. The response message will contain details about which part was not valid.

##### 404 Not found

A not found response might be returned if:
- The card for the given cardId could not be found
- The comment could not be found

### DELETE /cards/{cardId}/comments/{commentId} - Delete a comment

#### Request parameters

| Parameter | Type    | Description                             |
| --------- | ------- | --------------------------------------- |
| cardId    | Integer | The id of the card                      |
| commentId    | Integer | The id of the comment                      |

Deleting comments is limited to the current user being the same as the comment author specified in the `actorId` of the comment.

```
curl -X DELETE 'https://admin:admin@nextcloud/ocs/v2.php/apps/deck/api/v1.0/cards/12/comments' \
    -H 'Accept: application/json' -H 'OCS-APIRequest: true'
    -H 'Content-Type: application/json;charset=utf-8'
```

#### Response

A list of comments will be provided under the `ocs.data` key. If no or no more comments are available the list will be empty.

##### 200 Success

```
{
  "ocs": {
    "meta": {
      "status": "ok",
      "statuscode": 200,
      "message": "OK"
    },
    "data": []
  }
}
```

##### 400 Bad request

A bad request response is returned if invalid input values are provided. The response message will contain details about which part was not valid.

##### 404 Not found

A not found response might be returned if:
- The card for the given cardId could not be found
- The comment could not be found
