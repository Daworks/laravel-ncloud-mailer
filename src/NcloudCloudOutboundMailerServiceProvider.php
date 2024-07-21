<?php
	
	namespace Daworks\NcloudCloudOutboundMailer;
	
	use Illuminate\Support\ServiceProvider;
	use Illuminate\Support\Facades\Mail;
	
	class NcloudCloudOutboundMailerServiceProvider extends ServiceProvider
	{
		public function boot()
		{
			$this->publishes([
				__DIR__.'/config/ncloud-cloud-outbound-mailer.php' => config_path('ncloud-cloud-outbound-mailer.php'),
			]);
			
			Mail::extend('ncloud', function ($app) {
				$config = $app['config']['ncloud-cloud-outbound-mailer'];
				return new NcloudMailerDriver(
					$config['auth_key'],
					$config['service_secret']
				);
			});
		}
		
		public function register()
		{
			$this->mergeConfigFrom(
				__DIR__.'/config/ncloud-cloud-outbound-mailer.php', 'ncloud-cloud-outbound-mailer'
			);
		}
	}
