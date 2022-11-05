# fiverr-telegram-notification

This is a simple script that will send you a Telegram notification when you get a new message on Fiverr.

## Installation
clone this repository with:
```bash
git clone https://github.com/merchizm/fiverr-telegram-notification.git
```
then install the requirements with:
```bash
composer install
```

## Usage
1. Create a Telegram bot and get the token from @BotFather,
2. Create a database and get the credentials,
3. Find the IMAP information of your mail registered on Fiverr,
4. And create a `.env` file with the following content:
```dotenv
IMAP_HOST=YOUR HOST
IMAP_PORT=993
IMAP_USERNAME=YOUR USERNAME
IMAP_PASSWORD=YOUR PASSWORD

TELEGRAM_BOT_TOKEN= YOUR BOT TOKEN
TELEGRAM_USER_ID= YOUR TELEGRAM USER ID

MYSQL_HOST= YOUR MYSQL HOST
MYSQL_PORT=3306
MYSQL_USER= YOUR MYSQL USER
MYSQL_PASS= YOUR MYSQL PASSWORD
MYSQL_DATABASE= YOUR MYSQL DATABASE
```
## Cronjob's

### Scan
this cronjob will scan the inbox if there is a new message.

url: `yourwebsite.com/job/scan`

### Send

this cronjob will send the notification to your Telegram.

url: `yourwebsite.com/job/send`