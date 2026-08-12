---
title: Moderation Commands
commands:
  - name: Ban Player
    slug: ban-player
    syntax: /ban <player> [reason]
    description: Bans a player from the server.
    aliases:
      - ban
      - block
    category: Moderation
    params:
      player:
        type: string
        required: true
      reason:
        type: string
        required: false
---

# Moderation Commands

Operational commands used by moderators.

## /kick-player

```yaml
syntax: /kick <player> [reason]
description: Kicks a player from the server.
aliases:
  - kick
  - remove
category: Moderation
params:
  player:
    type: string
    required: true
```

## /mute-player

syntax: /mute <player> <minutes>
aliases: mute, silence
category: Moderation
Mutes a player for a limited duration.
