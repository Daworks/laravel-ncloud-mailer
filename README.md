# Ncloud Mailer for Laravel

이 패키지는 Laravel 11.x 이상에서 Ncloud Cloud Outbound Mailer를 사용할 수 있게 해주는 메일러 드라이버입니다.


## 요구사항

- PHP 8.1 이상
- Laravel 11.x


## 설치

Composer를 통해 패키지를 설치하세요:

````
composer require daworks/ncloud-cloud-outbound-mailer
````


## 설정

1. `.env` 파일에 Ncloud 인증 정보를 추가하세요:
   
   인증 정보는 ncloud에 로그인 후 계정 관리 > 인증키 관리 탭에 있는 Access Key ID와 Secret Key를 입력하세요.
```
NCLOUD_AUTH_KEY=access_key_id
NCLOUD_SERVICE_SECRET=secret_key
```



2. 설정 파일 퍼블리싱

```
php artisan vendor:publish --provider="Daworks\NcloudCloudOutboundMailer\NcloudCloudOutboundMailerServiceProvider" --tag=config
```


3. `config/mail.php`에서 새 메일러를 추가하세요:

```php
'mailers' => [
    'ncloud' => [
        'transport' => 'ncloud',
    ],
],

'default' => 'ncloud',
```

4. 설정파일을 캐싱하세요.
```
@php artisan config:cache
```


## 사용

일반적인 Laravel 메일 기능을 그대로 사용하면 됩니다. 예:

- Mailable을 이용하여 발송
```php
Mail::to($request->user())->send(new OrderShipped($order));
```

- Notification을 이용하여 발송
```php
Notification::route('mail', 'your@email.com')->notify(new TestNotification());
```
