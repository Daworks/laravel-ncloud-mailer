<?php

    namespace Daworks\NcloudCloudOutboundMailer;

    use Illuminate\Support\Facades\File;
    use Symfony\Component\Mailer\SentMessage;
    use Symfony\Component\Mailer\Transport\AbstractTransport;
    use Symfony\Component\Mime\Email;
    use Symfony\Component\Mime\MessageConverter;
    use GuzzleHttp\Client;
    use GuzzleHttp\Exception\GuzzleException;
    use GuzzleHttp\Psr7\Utils;

    class NcloudMailerDriver extends AbstractTransport
    {
        protected $base_uri = 'https://mail.apigw.ntruss.com';
        protected $apiEndpoint = '/api/v1/mails';
        protected $fileApiEndpoint = '/api/v1/files';
        protected $authKey;
        protected $serviceSecret;
        protected $client;

        public function __construct(string $authKey, string $serviceSecret)
        {
            parent::__construct();
            $this->authKey = $authKey;
            $this->serviceSecret = $serviceSecret;
            $this->client = new Client([
                'base_uri' => $this->base_uri
            ]);
        }

        protected function doSend(SentMessage $message): void
        {
            $email = MessageConverter::toEmail($message->getOriginalMessage());

            try {

                $attachments = $this->uploadAttachments($email);

                $timestamp = $this->getTimestamp();
                $signature = $this->makeSignature($timestamp, 'POST', $this->apiEndpoint);

                $response = $this->client->post($this->apiEndpoint, [
                    'headers' => [
                        'Content-Type'             => 'application/json',
                        'x-ncp-apigw-timestamp'    => $timestamp,
                        'x-ncp-iam-access-key'     => $this->authKey,
                        'x-ncp-apigw-signature-v2' => $signature,
                    ],
                    'json'    => $this->formatEmailData($email, $attachments),
                ]);

                if ($response->getStatusCode() !== 201) {
                    throw new \Exception('Failed to send email: ' . $response->getBody());
                }

            } catch (GuzzleException $e) {
                throw new \Exception('HTTP request failed: ' . $e->getMessage());
            }
        }

        protected function formatEmailData(Email $email, array $attachments): array
        {
            $data = [
                'senderAddress' => $email->getFrom()[0]->getAddress(),
                'senderName'    => $email->getFrom()[0]->getName(),
                'title'         => $email->getSubject(),
                'body'          => $email->getHtmlBody() ?? $email->getTextBody(),
                'recipients'    => [
                    [
                        'address' => $email->getTo()[0]->getAddress(),
                        'name'    => $email->getTo()[0]->getName(),
                        'type'    => 'R',
                    ]
                ],
                'individual'    => true,
                'advertising'   => false,
            ];

            if (!empty($attachments)) {
                $data['attachFileIds'] = $attachments;
            }

            return $data;
        }

        protected function uploadAttachments(Email $email): array
        {

            $timestamp = $this->getTimestamp();
            $attachments = [];

            foreach ($email->getAttachments() as $attachment) {
                try {

                    $delimiter = uniqid();
                    $data = '';
                    $data .= "--" . $delimiter . "\r\n";
                    $data .= 'Content-Disposition: form-data; name="fileList";' . ' filename="' . $attachment->getFilename() . '"' . "\r\n";
                    $data .= 'Content-Type: ' . $attachment->getMediaType() . '/' . $attachment->getMediaSubtype() . "\r\n";
                    $data .= "\r\n";
                    $data .= $attachment->bodyToString() . "\r\n";
                    $data .= "--" . $delimiter . "--\r\n";

                    $headers = [
                        'accept:application/json',
                        'Content-Type:multipart/form-data;boundary='.$delimiter,
                        'x-ncp-apigw-timestamp: ' . $timestamp,
                        'x-ncp-iam-access-key: ' . $this->authKey,
                        'x-ncp-apigw-signature-v2: ' . $this->makeSignature($timestamp, 'POST', $this->fileApiEndpoint),
                        'x-ncp-lang: ' . 'ko_KR',
                        'Content-Length: ' . strlen($data)
                    ];

                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $this->base_uri . $this->fileApiEndpoint);
                    curl_setopt($ch, CURLOPT_HEADER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $data); // 파일내용
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                    $response = curl_exec($ch);
                    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                    $headerContents = substr($response, 0, $headerSize);
                    $bodyContents = substr($response, $headerSize);
                    $result = json_decode(urldecode($bodyContents), true);

                    curl_close($ch);

                    $attachments[] = $result['files'][0]['fileId'];


                } catch (\Exception $e) {
                    throw new \Exception('HTTP request failed: ' . $e->getMessage());
                }
            }

            return $attachments;

        }

        protected function makeSignature($timestamp, $method = 'POST', $uri = '/api/v1/mails')
        {
            $space = " ";
            $newLine = "\n";
            $accessKey = $this->authKey;
            $secretKey = $this->serviceSecret;

            $hmac = $method . $space . $uri . $newLine . $timestamp . $newLine . $accessKey;

            return base64_encode(hash_hmac('sha256', $hmac, $secretKey, true));
        }

        protected function getTimestamp()
        {
            return round(microtime(true) * 1000);
        }

        public function __toString(): string
        {
            return 'ncloud';
        }
    }
