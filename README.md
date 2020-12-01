# hellogithub_bot
Get GitHub notifications via Telegram bot.

### How does it work?
You can add webhook urls to your repositories, which get called in case of specific events. 

I wanted my notifications to be only one-way, as simple as possible and in Telegram.

### Advantages
* No Signup
* Notifications only, no write access
* No external Services like Zapier or IFTTT required
* Can self-host on shared hosting plans, no Node.js required. Bot is written in PHP.

## You can use my already existing bot
1. 🔗 Go to [t.me/hellogithub_bot](https://t.me/hellogithub_bot)
2. 🤖 Start Bot and copy webhook url
3. 📋 Add webhook url to your repository
4. ✅ Get notified!

## ... or you can host your own
1. Create a new bot with [@BotFather](https://t.me/BotFather)
1. Upload ```bot.php``` to your webspace
2. Add ```config.php``` containing your Bot-Token
1. Tell Telegram to use your Bot with ```bot.php?webhook&token=<yourbottoken>```
1. 🤖 Start your Bot and copy webhook url
3. 📋 Add webhook url to your repository
4. ✅ Get notified!

## Supported GitHub Events
Hellogithub_bot currently supports the following types of [HTTP_X_GITHUB_EVENT](https://docs.github.com/en/free-pro-team@latest/developers/webhooks-and-events/webhook-events-and-payloads)
* ```push```
* ```ping```
* ```issues```
* ```member```
* ```deploy_key```
* ```pull_request```
* ```delete```
* ```create```
