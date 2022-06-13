<?php
declare(strict_types = 1);
namespace Inspire\Mailer\Maildocker;

use Nette\FileNotFoundException;

/**
 * Description of Message
 * Based in https://github.com/maildocker/maildocker-php
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
    protected ?string $text = null;

    /**
     * HTML message contents
     *
     * @var array
     */
    protected string $html = '';

    /**
     * Message template
     *
     * @var array
     */
    protected ?string $template = null;

    /**
     * Reply TO field
     *
     * @var string|null
     */
    protected ?string $replyTo = null;

    /**
     * Date field
     *
     * @var string|null
     */
    protected ?string $date = null;

    /**
     * Custom headers
     *
     * @var string|null
     */
    protected ?string $headers = null;

    /**
     * Images list
     *
     * @var array
     */
    protected array $images = [];

    /**
     * Attachments list
     *
     * @var array
     */
    protected array $attachments = [];

    /**
     * Vars to merge to message
     *
     * @var array
     */
    protected array $mergeVars = [];

    /**
     * Build message as array (PHP 8.1 compatibility)
     *
     * @return array
     */
    public function build(): array
    {
        return [
            'from' => $this->from,
            'to' => $this->to,
            'cc' => $this->cc,
            'bcc' => $this->bcc,
            'subject' => $this->subject,
            'text' => $this->text,
            'html' => $this->html,
            'template' => $this->template,
            'replyTo' => $this->replyTo,
            'date' => $this->date,
            'headers' => $this->headers,
            'images' => $this->images,
            'attachments' => $this->attachments,
            'mergeVars' => $this->mergeVars
        ];
    }

    /**
     * Set FROM address
     *
     * @param string $address
     * @param string $name
     * @return Message
     */
    public function from(string $address, ?string $name = null): Message
    {
        $this->from = array(
            'email' => $address
        );
        if ($name && ! empty($name)) {
            $this->from['name'] = $name;
        }
        return $this;
    }

    /**
     * Add email data from any kind (To, CC, BCC)
     *
     * @param string $field
     * @param string $address
     * @param string $name
     */
    protected function addMail(string $field, string $address, ?string $name = null)
    {
        if (is_string($address)) {
            $mail = array(
                'email' => $address
            );
            if ($name && ! empty($name)) {
                $mail['name'] = $name;
            }
            $this->{$field}[] = $mail;
        } else if (is_array($address)) {
            foreach ($address as $email) {
                $this->addMail($field, $email);
            }
        }
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
     * Setmessage message template
     *
     * @param string $template
     * @return Message
     */
    public function setTemplate(string $template): Message
    {
        $this->template = $template;
        return $this;
    }

    /**
     * Set message alternative text
     *
     * @param string $text
     * @return Message
     */
    public function setText(string $text): Message
    {
        $this->text = $text;
        return $this;
    }

    /**
     * Set HTML mail contents
     *
     * @param string $html
     * @return Message
     */
    public function setHtml(string $html): Message
    {
        $this->html = $html;
        return $this;
    }

    /**
     * Set reply to
     *
     * @param string $replyto
     * @return Message
     */
    public function setReplyto(string $replyto): Message
    {
        $this->replyTo = $replyto;
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
     * Add merge vars
     *
     * @param array $mergeVars
     * @return Message
     */
    public function addVars(array $mergeVars): Message
    {
        $this->mergeVars = array_merge($this->mergeVars, $mergeVars);
        return $this;
    }

    /**
     * Add custom headers
     *
     * @param array $headers
     * @return Message
     */
    public function setHeaders(array $headers): Message
    {
        $this->headers = json_encode($headers);
        return $this;
    }

    /**
     * Add a single file attachment
     *
     * @param string $file
     * @param string $name
     * @throws FileNotFoundException
     */
    protected function addFile(string $field, string $file, ?string $name = null)
    {
        if (! file_exists($file)) {
            throw new FileNotFoundException("File '{$file}' does not exists!");
        }
        $handle = fopen($file, 'rb');
        $this->{$field}[] = [
            'name' => $name ?? basename($file),
            'type' => mime_content_type($file),
            'content' => base64_encode(fread($handle, filesize($file)))
        ];
    }

    /**
     * Add a single file attachment
     *
     * @param string $body
     * @param string $name
     * @param string $contentType
     */
    public function addAttachmentContents(string $body, string $name, string $contentType)
    {
        $this->attachments[] = [
            'name' => $name,
            'type' => $contentType,
            'content' => $body
        ];
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
            foreach ($files as $name => $file) {
                $this->addFile('attachments', $file, is_numeric($name) ? null : $name);
            }
        }
        return $this;
    }

    /**
     * Add images
     *
     * @param array $images
     * @return Message
     */
    public function addImages(array $images): Message
    {
        if (is_array($images)) {
            foreach ($images as $name => $file) {
                $this->addFile('images', $file, is_numeric($name) ? null : $name);
            }
        }
        return $this;
    }
}