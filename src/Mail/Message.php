<?php
declare(strict_types = 1);
namespace Inspire\Mailer\Mail;

use Inspire\Support\Message\System\SystemMessage;
use Symfony\Component\Mailer\ {
    Mailer,
    Transport
};
use Inspire\Config\JsonValidator;
use Inspire;
use Ramsey\Uuid\Provider\Dce\SystemDceSecurityProvider;

/**
 * Description of Mail
 *
 * @author aalves
 */
class Message
{

    /**
     * FROM address
     *
     * @var array
     */
    protected array $from = [];

    /**
     * TO address list
     *
     * @var array
     */
    protected array $to = [];

    /**
     * CC address list
     *
     * @var array
     */
    protected array $cc = [];

    /**
     * BCC address list
     *
     * @var array
     */
    protected array $bcc = [];

    /**
     * ReplyTo field
     *
     * @var array|null
     */
    protected array $replyTo = [];

    /**
     * Subject of message
     *
     * @var array
     */
    protected string $subject = '';

    /**
     * Alternative text
     *
     * @var string
     */
    protected ?array $text = [];

    /**
     * HTML message contents
     *
     * @var array
     */
    protected array $html = [];

    /**
     * Date field
     *
     * @var string|null
     */
    protected ?string $date = null;

    /**
     * Attachments list
     *
     * @var array
     */
    protected array $attachments = [];

    /**
     * Priority
     *
     * @var int
     */
    protected int $priority = 5;

    /**
     * Return path to send message on send
     *
     * @var string|NULL
     */
    protected ?string $returnPath = null;

    /**
     * Set FROM address
     *
     * @param string $address
     * @param string $name
     * @return Message
     */
    public function from(string $address, ?string $name = null): Message
    {
        $this->from = [
            'address' => $address
        ];
        if ($name && ! empty($name)) {
            $this->from['name'] = $name;
        }
        return $this;
    }

    /**
     * Add email data from any kind (To, CC, BCC, replyTo)
     *
     * @param string $field
     * @param string $address
     * @param string $name
     */
    protected function addMail(string $field, string $address, ?string $name = null, bool $unique = false): Message
    {
        if (! in_array($field, [
            'to',
            'cc',
            'bcc',
            'replyTo'
        ])) {
            throw new \Exception("Invalid mail field type: {$field}");
        }
        $mail = [
            'address' => $address
        ];
        if ($name && ! empty($name)) {
            $mail['name'] = $name;
        }
        if ($unique) {
            $this->{$field} = $mail;
        } else {
            $this->{$field}[] = $mail;
        }
        return $this;
    }

    /**
     * Add email data from any kind (To, CC, BCC, replyTo)
     *
     * @param string $field
     * @param array $mails
     */
    protected function addMails(string $field, array $mails): Message
    {
        if (! in_array($field, [
            'to',
            'cc',
            'bcc',
            'replyTo'
        ])) {
            throw new \Exception("Invalid mail field type: {$field}");
        }
        foreach ($mails as $address => $name) {
            if (! empty($address)) {
                $this->addMail($field, $address, $name);
            } else {
                $this->addMail($field, $name);
            }
        }
        return $this;
    }

    /**
     * Add TO contact
     *
     * @param string $to
     * @param string $name
     * @return Message
     */
    public function addTo(string $to, ?string $name = null): Message
    {
        $this->addMail('to', $to, $name);
        return $this;
    }

    /**
     * Add CC contact
     *
     * @param string $cc
     * @param string $name
     * @return Message
     */
    public function addCc(string $cc, ?string $name = null): Message
    {
        $this->addMail('cc', $cc, $name);
        return $this;
    }

    /**
     * Add BCC contact
     *
     * @param string $bcc
     * @param string $name
     * @return Message
     */
    public function addBcc(string $bcc, ?string $name = null): Message
    {
        $this->addMail('bcc', $bcc, $name);
        return $this;
    }

    /**
     * Set reply to
     *
     * @param string $replyto
     * @param string $name
     * @return Message
     */
    public function setReplyto(string $replyto, ?string $name = null): Message
    {
        $this->addMail('replyTo', $replyto, $name, true);
        return $this;
    }

    /**
     * Set message subject
     *
     * @param string $subject
     * @return Message
     */
    public function setSubject(string $subject): Message
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * Set message alternative text
     *
     * @param string $text
     * @return Message
     */
    public function setText(string $text, string $charset = 'utf-8'): Message
    {
        $this->text = [
            'text' => $text,
            'charset' => $charset
        ];
        return $this;
    }

    /**
     * Set HTML mail contents
     *
     * @param string $html
     * @return Message
     */
    public function setHtml(string $html, string $charset = 'utf-8'): Message
    {
        $this->html = [
            'text' => $html,
            'charset' => $charset
        ];
        return $this;
    }

    /**
     * Set email date
     *
     * @param string $date
     * @return Message
     */
    public function setDate(string $date): Message
    {
        $this->date = $date;
        return $this;
    }

    /**
     * Add a list of files
     *
     * @param array $files
     * @return Message
     */
    public function addAttachment(array $files): Message
    {
        if (is_array($files)) {
            if (isset($files[0])) {
                foreach ($files as $file) {
                    $this->attachments[] = $file;
                }
            } else {
                $this->attachments[] = $files;
            }
        }
        return $this;
    }

    /**
     * Send email
     *
     * @param array $configMail
     * @return SystemMessage
     */
    public function send(array $configMail): ?SystemMessage
    {
        try {
            /**
             * Validate mail data
             */
            $validate = $this->validate($configMail);
            if (! $validate->isOk()) {
                return $validate;
            }
            /**
             * Maildocker service
             */
            if ($configMail['provider'] == 'maildocker') {
                /**
                 * Maildocker message
                 */
                $email = (new Inspire\Mailer\Maildocker\Message())->setSubject($this->subject)
                    ->setText($this->text['text'] ?? '')
                    ->setHtml($this->html['text'] ?? '');
                /**
                 * Set FROM
                 */
                if (filter_var($this->from['address'], FILTER_VALIDATE_EMAIL)) {
                    $email->from($this->from['address'], $this->from['name'] ?? $this->from['address']);
                }
                $hasTo = false;
                /**
                 * Add TO
                 */
                foreach ($this->to as $to) {
                    if (filter_var($to['address'], FILTER_VALIDATE_EMAIL)) {
                        $email->addTo($to['address'], $to['name']);
                        $hasTo = true;
                    }
                }
                /**
                 * Add CC
                 */
                foreach ($this->cc as $cc) {
                    if (filter_var($cc['address'], FILTER_VALIDATE_EMAIL)) {
                        if (! $hasTo) {
                            $email->addTo($cc['address'], $cc['name']);
                            $hasTo = true;
                        } else {
                            $email->addCc($cc['address'], $cc['name']);
                        }
                    }
                }
                /**
                 * Add BCC
                 */
                foreach ($this->bcc as $bcc) {
                    if (filter_var($bcc['address'], FILTER_VALIDATE_EMAIL)) {
                        if (! $hasTo) {
                            $email->addTo($bcc['address'], $bcc['name']);
                            $hasTo = true;
                        } else {
                            $email->addBcc($bcc['address'], $bcc['name']);
                        }
                    }
                }
                /**
                 * Set Reply-to
                 */
                if (! empty($this->replyTo) && filter_var($this->replyTo['address'], FILTER_VALIDATE_EMAIL)) {
                    $email->setReplyto($this->replyTo['address'], $this->replyTo['name'] ?? $this->replyTo['address']);
                }
                /**
                 * Add attachments
                 */
                foreach ($this->attachments as $attachment) {
                    if (isset($attachment['path'])) {
                        $email->addAttachment([
                            $attachment['path']
                        ]);
                    } else {
                        $email->addAttachmentContents($attachment['body'], $attachment['name'], $attachment['content-type']);
                    }
                }
                /**
                 * Create a client
                 */
                $mailer = new \Inspire\Mailer\Maildocker\Client($configMail['access_key'], $configMail['secret_key']);
                /**
                 * Send email
                 */
                return $mailer->send($email);
            } else {
                $email = new \Symfony\Component\Mime\Email();
                // // ->sender(empty($this->sender) ? $configMail->email : $this->sender)
                $email->subject($this->subject)
                    ->priority(empty($this->priority) ? $configMail->prioridade : $this->priority)
                    ->html($this->html['text'], $this->html['charset'])
                    ->text($this->html['text'], $this->html['charset']);
                /**
                 * Set FROM
                 */
                if (filter_var($this->from['address'], FILTER_VALIDATE_EMAIL)) {
                    $email->addFrom(new \Symfony\Component\Mime\Address($this->from['address'], $this->from['name'] ?? $this->from['address']));
                }
                // if ($this->return_path !== null && filter_var($this->return_path, FILTER_VALIDATE_EMAIL)) {
                // $email->returnPath($this->return_path);
                // }
                /**
                 * Set TO
                 */
                foreach ($this->to as $to) {
                    if (filter_var($to['address'], FILTER_VALIDATE_EMAIL)) {
                        $email->addTo(new \Symfony\Component\Mime\Address($to['address'], $to['name'] ?? $to['address']));
                    }
                }
                /**
                 * Set CC
                 */
                foreach ($this->cc as $cc) {
                    if (filter_var($cc['address'], FILTER_VALIDATE_EMAIL)) {
                        $email->addCc(new \Symfony\Component\Mime\Address($cc['address'], $cc['name'] ?? $cc['address']));
                    }
                }
                /**
                 * Set BCC
                 */
                foreach ($this->bcc as $bcc) {
                    if (filter_var($bcc['address'], FILTER_VALIDATE_EMAIL)) {
                        $email->addBcc(new \Symfony\Component\Mime\Address($bcc['address'], $bcc['name'] ?? $bcc['address']));
                    }
                }
                /**
                 * Set Reply-to
                 */
                if (filter_var($this->replyTo['address'], FILTER_VALIDATE_EMAIL)) {
                    $email->addReplyTo(new \Symfony\Component\Mime\Address($this->replyTo['address'], $this->replyTo['name'] ?? $this->replyTo['address']));
                }
                /**
                 * Set Attachments
                 */
                foreach ($this->attachments as $attach) {
                    if (isset($attach['path'])) {
                        $email->attachFromPath($attach['path'], $attach['name'] ?? null, $attach['content-type'] ?? null);
                    } else {
                        $email->attach($attach['body'], $attach['name'] ?? null, $attach['content-type'] ?? null);
                    }
                }
                /**
                 * Configurações de drivers
                 */
                $transport = null;
                switch ($configMail['provider']) {
                    /**
                     * SMTP transport
                     */
                    case 'smtp':
                        $transport = new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport($configMail['domain'], $configMail['port'] ?? 0, $configMail['$configMail'] ?? null);
                        $transport->setUsername($configMail['username']);
                        $transport->setPassword($configMail['password']);
                        break;
                    /**
                     * SES transport
                     */
                    case 'ses':
                        switch ($configMail['driver']) {
                            case 'api':
                                $transport = Transport::fromDsn("ses+api://{$configMail['access_key']}:{$configMail['secret_key']}@default");
                                break;
                            case 'http':
                                $transport = Transport::fromDsn("ses+https://{$configMail['access_key']}:{$configMail['secret_key']}@default");
                                break;
                            case 'smtp':
                                $transport = Transport::fromDsn("ses+smtp://{$configMail['username']}:{$configMail['password']}@default");
                                break;
                        }
                        break;
                    /**
                     * Gmail transport
                     */
                    case 'gmail':
                        $transport = new \Symfony\Component\Mailer\Bridge\Google\Transport\GmailSmtpTransport($configMail['username'], $configMail['password']);
                        break;
                    /**
                     * Mailgun transport
                     */
                    case 'mailgun':
                        switch ($configMail['driver']) {
                            case 'api':
                                $transport = Transport::fromDsn("mailgun+api://{$configMail['key']}:{$configMail['domain']}@default");
                                break;
                            case 'http':
                                $transport = Transport::fromDsn("mailgun+https://{$configMail['key']}:{$configMail['domain']}@default");
                                break;
                            case 'smtp':
                                $transport = Transport::fromDsn("mailgun+smtp://{$configMail['username']}:{$configMail['password']}@default");
                                break;
                        }
                        break;
                    /**
                     * Mailjet transport
                     */
                    case 'mailjet':
                        switch ($configMail['driver']) {
                            case 'api':
                                $transport = Transport::fromDsn("mailjet+api://{$configMail['access_key']}:{$configMail['secret_key']}@default");
                                break;
                            case 'smtp':
                                $transport = Transport::fromDsn("mailjet+smtp://{$configMail['access_key']}:{$configMail['secret_key']}@default");
                                break;
                        }
                        break;

                    /**
                     * Postmark transport
                     */
                    case 'postmark':
                        switch ($configMail['driver']) {
                            case 'api':
                                $transport = Transport::fromDsn("postmark+api://{$configMail['key']}@default");
                                break;
                            case 'smtp':
                                $transport = Transport::fromDsn("postmark+smtp://{$configMail['id']}@default");
                                break;
                        }
                        break;

                    /**
                     * SendGrid transport
                     */
                    case 'sendgrid':
                        switch ($configMail['driver']) {
                            case 'smtp':
                                $transport = Transport::fromDsn("sendgrid+smtp://{$configMail['key']}@default");
                                break;
                            case 'api':
                                $transport = Transport::fromDsn("sendgrid+api://{$configMail['key']}@default");
                                break;
                        }
                        break;
                    /**
                     * Sendinblue transport
                     */
                    case 'sendinblue':
                        switch ($configMail['driver']) {
                            case 'api':
                                $transport = Transport::fromDsn("sendinblue+smtp://{$configMail['key']}@default");
                                break;
                            case 'smtp':
                                $transport = Transport::fromDsn("sendinblue+api://{$configMail['username']}:{$configMail['password']}@default");
                                break;
                        }
                        break;
                    /**
                     * Sendinblue transport
                     */
                    case 'ohmysmtp':
                        switch ($configMail['driver']) {
                            case 'api':
                                $transport = Transport::fromDsn("ohmysmtp+smtp://{$configMail['api_token']}@default");
                                break;
                            case 'smtp':
                                $transport = Transport::fromDsn("ohmysmtp+api://{$configMail['api_token']}@default");
                                break;
                        }
                        break;
                }
                if (! $transport !== null) {
                    // $mailer = new Mailer($transport);
                    $resp = $transport->send($email);
                    // var_dump($resp->getDebug());
                    return new SystemMessage('Mail sent', '1');
                } else {
                    return new SystemMessage("Invalid provider: {$configMail['provider']}", '0', SystemMessage::MSG_ERROR);
                }
            }
        } catch (\Exception $ex) {
            return new SystemMessage($ex->getMessage(), '0', SystemMessage::MSG_ERROR);
        }
    }

    /**
     * Validate data before trying to send
     *
     * @param array $configMail
     * @return SystemMessage
     */
    private function validate(array $configMail): SystemMessage
    {
        /**
         * Validate configuration
         */
        $schema = dirname(dirname(__DIR__)) . "/schemas/provider_config.json";
        if (! JsonValidator::validateJson(json_encode([
            $configMail
        ]), $schema)) {
            $syserror = new SystemMessage('Invalid configuration', //
            '0', //
            SystemMessage::MSG_ERROR, //
            false);
            $syserror->setExtra(JsonValidator::getReadableErrors());
            return $syserror;
        }

        $mail = [
            'from' => $this->from,
            'replyTo' => empty($this->replyTo) ? null : $this->replyTo,
            'to' => $this->to,
            'cc' => $this->cc,
            'bcc' => $this->bcc,
            'subject' => $this->subject,
            'text' => $this->text,
            'html' => $this->html,
            'attachments' => $this->attachments
        ];
        /**
         * Validating mail data
         */
        $schema = dirname(dirname(__DIR__)) . "/schemas/mail.json";
        if (! JsonValidator::validateJson(json_encode($mail), $schema)) {
            $syserror = new SystemMessage('Invalid message', //
            '0', //
            SystemMessage::MSG_ERROR, //
            false);
            $syserror->setExtra(JsonValidator::getReadableErrors());
            return $syserror;
        }
        /**
         * Mail and configuration succefully validated
         */
        return new SystemMessage('OK', 'OK');
    }
}

