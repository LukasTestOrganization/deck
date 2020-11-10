## Introduction
### What about Deck ?
You may know Kanban website like Trello? Deck is about the same thing but secured and respectful of your privacy!  
Integrated in Nextcloud, you can easily manage your projects while having your data secured.

### Use cases
Project management, time management or ideation, Deck makes it easier for you to manage your work.

## Using Deck
Overall, Deck is easy to use. You can create boards, add users, share the Deck, work collaboratively and in real time.

1. [Create my first board](#1-create-my-first-board)
2. [Create stacks and cards](#2-create-stacks-and-cards)
3. [Handle cards options](#3-handle-cards-options)
4. [Archive old tasks](#4-archive-old-tasks)
5. [Manage your board](#5-manage-your-board)
6. [New owner for the deck entities](#6-new-owner-for-the-deck-entities)

### 1. Create my first board
In this example, we're going to create a board and share it with an other nextcloud user.

![Gif for creating boards](resources/gifs/EN_create_board.gif)


### 2. Create stacks and cards
Stacks are simply columns with list of cards. It can represent a category of tasks or an y step in your projects for example.   
**Check this out :**

![Gif for creating columns](resources/gifs/EN_create_columns.gif)

What about the cards? Cards are tasks, objects or ideas that fit into a stack. You can put a lot of cards in a stack! An infinity? Who knows! Who knows!   

And all the magic of this software consists on moving your cards from a stack to an other.  
**Check this out :**

![Gif for creating tasks](resources/gifs/EN_create_task.gif)

### 3. Handle cards options
Once you have created your cards, you can modify them or add options by clicking on them. So, what are the options? Well, there are several of them:

- Tag Management
- Assign a card to a user (s·he will receive a notification)
- Render date, or deadline

![Gif for puting infos on tasks](resources/gifs/EN_put_infos.gif)

And even :

- Description in markdown language
- Attachment - *you can leave a document, a picture or some other bonus like that.*

![Gif for puting infos on tasks 2](resources/gifs/EN_put_infos_2.gif)

### 4. Archive old tasks
Once finished or obsolete, a task could be archived. The tasks is not deleted, it's just archived, and you can retrieve it later

![Gif for puting infos on tasks 2](resources/gifs/EN_archive.gif)

### 5. Manage your board
You can manage the settings of your Deck once you are inside it, by clicking on the small wheel at the top right.
Once in this menu, you have access to several things:

- Sharing
- Tags
- Deleted objects
- Timeline

The **sharing tab** allows you to add users or even groups to your boards.  
**Tags** allows you to modify the tags available for the cards.  
**Deleted objects** allows you to return previously deleted stacks or cards.  
The **Timeline** allows you to see everything that happened in your boards. Everything!

### 6. New owner for the deck entities
You can transfer ownership of boards, cards, etc to a new user, using `occ` command `deck:transfer-ownership`  
`$ php occ deck:transfer-ownership owner newOwner`
