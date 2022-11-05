<?php

namespace ROCKS;

use DateTime;
use Exception;
use PhpImap\Exceptions\InvalidParameterException;
use PhpImap\IncomingMail;
use PhpImap\Mailbox;

class mailParser
{

    private Mailbox $mailbox;
    private DateTime $date;

    /**
     * @throws InvalidParameterException
     * @throws Exception
     */
    public function __construct()
    {
        $this->date = new DateTime('yesterday');
        $this->mailbox = new Mailbox(
            '{'.$_ENV['IMAP_HOST'].':'.$_ENV['IMAP_PORT'].'/imap/ssl}INBOX',
            $_ENV['IMAP_USERNAME'],
            $_ENV['IMAP_PASSWORD'],
            null,
            'UTF-8',
            true,
            false
        );
        $this->mailbox->setAttachmentsIgnore(true);
    }

    public function getMailIds(): array|bool
    {
        try {
            $mailsIds = $this->mailbox->searchMailbox('SINCE "'.$this->date->format('j F Y').'"');

            if(!$mailsIds) {
                return false;
            }

        } catch(Exception $ex) {
            echo "IMAP connection failed: " . implode(",", $ex->getErrors('all'));
            return false;
        }
        return $mailsIds;
    }

    public function getMail($id): IncomingMail
    {
        $mail = $this->mailbox->getMail($id);
        if(!$mail->isSeen)
            $this->mailbox->markMailAsRead($id);
        return $mail;
    }
}