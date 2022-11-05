<?php

namespace ROCKS;

use Dotenv\Dotenv;
use Exception;
use PDO;
use PDOException;

class app
{
    /**
     * @var telegramBot
     */
    private telegramBot $telegram_bot;
    /**
     * @var mailParser
     */
    private mailParser $mail_parser;
    /**
     * @var PDO
     */
    private PDO $db;
    /**
     * for debug purposes
     * @var float
     */
    private float $time_start;
    /**
     * blacklist of previously read emails
     * @var array
     */
    private array $blacklist = [];

    /**
     * regex pattern to get username over email
     * @var string
     */
    private string $username_pattern = '/received messages from ([^"]+)<\//';
    /**
     * regex pattern to get gig over email
     * @var string
     */
    private string $gig_pattern = '/Gig,([^"]+):/';
    /**
     * regex pattern to get pm over email
     * @var string
     */
    private string $message_pattern = '@<td class="content" style=".*">([^"]+)</td>@m';
    /**
     * regex pattern to get message url over email
     * @var string
     */
    private string $message_url_pattern = '/href="([^"]+)" target="_blank"/';


    /**
     * @throws Exception
     */
    public function __construct(private readonly string $dir, private readonly bool $debug = false)
    {
        if($this->debug)
            $this->time_start = microtime(true);
        // check requirements
        if (!function_exists('imap_open'))
            throw new Exception('IMAP extension is not installed!');

        if (!function_exists('curl_init'))
            throw new Exception('CURL extension is not installed!');

        // initialize classes
        $dotenv = Dotenv::createImmutable($this->dir);
        $dotenv->load();
        $this->telegram_bot = new telegramBot();
        $this->mail_parser = new mailParser();

        // initialize database
        $this->connect();
        if ($this->checkDb() <= 0)
            $this->createDb();
    }

    /**
     * launches the application
     * @throws Exception
     */
    public function run(): void
    {
        $url = $this->parseUrlParameters();
        $route = (isset($url[key($url)])) ? htmlspecialchars(current($url), ENT_QUOTES) : 'index';

        switch (strtolower($route)) {
            case "job":
                if(!empty(isset($url[1])) and $url[1] == "scan"):
                    // load lists
                    $this->loadBlacklist();
                    // start scan
                    print(($this->scan()) ? "Scan job completed successfully!" : "Scan job failed!");
                elseif(!empty(isset($url[1])) and $url[1] == "send"):
                    $this->send();
                endif;
                break;
            default:
            echo 'application successfully running 👍🏻';
            break;
        }
    }

    private function parseUrlParameters(): array
    {
        return (isset($_GET['page'])) ? explode('/', filter_var(rtrim($_GET['page'], '/'), FILTER_SANITIZE_URL)) : [];
    }

    private function pregMatch(string $pattern, string $string): array
    {
        preg_match($pattern, $string, $matches, PREG_OFFSET_CAPTURE);
        return $matches;
    }

    /**
     * scan for new emails
     * @return bool
     */
    private function scan(): bool
    {
        $mailIds = $this->mail_parser->getMailIds();
        if(is_bool($mailIds) === false && count($mailIds) >= 1){
            foreach ($mailIds as $mailId) {
                if(in_array($mailId, array_values($this->blacklist)))
                    continue;

                $this->addBlacklist($mailId);
                $mail = $this->mail_parser->getMail($mailId);

                if($mail->fromAddress !== "noreply@e.fiverr.com" || $mail->fromName !== "Fiverr")
                    continue;


                if(!str_contains($mail->subject, 'received messages from'))
                    continue;

                $mail_content = $mail->textHtml;
                try{
                    $username = $this->pregMatch($this->username_pattern, $mail_content)[1][0];
                    $gig = $this->pregMatch($this->gig_pattern, $mail_content)[1][0];
                    $message = $this->pregMatch($this->message_pattern, $mail_content)[1][0];
                    $message_url = $this->pregMatch($this->message_url_pattern, $mail_content)[1][0];

                    if(isset($username) and isset($gig) and isset($message) and isset($message_url)){
                        $stmt = $this->db->prepare('INSERT INTO mails (id, subject, _from, _gig, _message, _message_url) VALUES (?, ?, ?, ?, ?, ?);');
                        return $stmt->execute([
                            $mailId,
                            $mail->subject,
                            $username,
                            $gig,
                            trim($message),
                            trim($message_url)
                        ]);
                    }
                }catch(Exception $ex){
                    //if($this->debug)
                        echo("Error: {$ex->getMessage()} \r\n");
                    return false;
                }
            }
            return true;
        }else
            return false;
    }

    /**
     * mark $id as read
     * @param $id
     * @return void
     */
    private function markTrue($id): void
    {
        $this->db->exec('UPDATE mails SET status = 1 WHERE id = ' . $id . ';');
    }
    /**
     * send notification on telegram
     * @return void
     */
    private function send() : void
    {
        $stmt = $this->db->query('SELECT * FROM mails WHERE status = 0');
        $mails = $stmt->fetchAll();
        foreach ($mails as $mail) {
            $this->markTrue($mail['id']);
            $this->telegram_bot->sendNotification($mail['_from'], $mail['_gig'], $mail['_message'], $mail['_message_url']);
        }
    }

    /**
     * connect to database
     * @throws Exception
     */
    private function connect(): void
    {
        try {
            $this->db = new PDO('mysql:host=' . $_ENV['MYSQL_HOST'] . ':' . $_ENV['MYSQL_PORT'] . ';dbname=' . $_ENV['MYSQL_DATABASE'], $_ENV['MYSQL_USER'], $_ENV['MYSQL_PASS']);
            $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException) {
            throw new Exception('check database connection! ', '1000');
        }
    }

    /**
     * check if database tables exists
     * @return int
     */
    private function checkDb(): int
    {
        $stmt = $this->db->prepare('SELECT count(*) AS TOTALNUMBEROFTABLES FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ?;');
        $stmt->execute([$_ENV['MYSQL_DATABASE']]);
        return $stmt->fetch()['TOTALNUMBEROFTABLES'];
    }

    /**
     * create database tables
     * @return void
     */
    private function createDb(): void
    {
        $this->db->beginTransaction();
        try{
            $this->db->exec("create table mails(id int auto_increment not null, subject varchar(150) not null, status tinyint(1) default 0 not null, _gig varchar(120) not null, _from varchar(60) not null, _message varchar(1200) not null, _message_url varchar(320) not null, constraint mails_pk primary key (id));");
            $this->db->exec("create table blacklist(id int not null, constraint blacklist_pk primary key (id));");
        }catch (Exception $e){
            echo "Failed: " . $e->getMessage();
            $this->db->rollback();
        }
    }

    /**
     * load blacklist
     * @return void
     */
    private function loadBlacklist() : void
    {
        $stmt = $this->db->query('SELECT id FROM blacklist');
        foreach ($stmt->fetchAll() as $item) {
            $this->blacklist[] = (int)$item['id'];
        }
    }

    /**
     * add $mailId to blacklist
     * @param mixed $mailId
     * @return void
     */
    private function addBlacklist(mixed $mailId): void
    {
        $this->db->exec('INSERT INTO blacklist(id) VALUES(' . $mailId . ')');
    }

    public function __destruct()
    {
        if($this->debug){
            $time_end = microtime(true);
            print "\r\n Processing time:".$time_end - $this->time_start;
        }

    }
}
