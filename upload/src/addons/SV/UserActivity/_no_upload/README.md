# XenForo2-UserActivity

Displays user activity below content.

Supported content:
- Threads
- Conversations
- Reports
- [NixFifty's Tickets](https://xenforo.com/community/resources/tickets.4549/)
- [NixFifty's Calendar](https://xenforo.com/community/resources/calendar.7524/)

It is recommended (but not required) that [Redis Cache add-on](https://xenforo.com/community/resources/redis-cache-by-xon.5562/) be installed **and configured** as the cache provider

## Caching context support

Use the key *userActivity* with [Cache context](https://xenforo.com/xf2-docs/manual/cache/#cache-contexts) to dedicate a redis instance to just User Activity

## Permissions/Options
- View user activity counters
- View users who are viewing content
- View user names/avatars in activity block

# Customizing user activity block with no users
The empty phrase `svUserActivity_viewing_users_are_empty` can be set, and it can be styled using the css selector `#uaThreadViewContainer .empty-list`

## Differences in guest counts
This add-on counts guests grouped by IP address, while XenForo counts guests by session. Both are valid ways of counting guests.