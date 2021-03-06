<?php

namespace Required\Digest\Tests;

use MockPHPMailer;
use Required\Digest\Event\Registry;
use Required\Digest\Queue;
use WP_UnitTestCase;
use MockAction;

class Plugin extends WP_UnitTestCase {
	public function test_plugin_is_activated() {
		$this->assertTrue( class_exists( 'Required\\Digest\\Plugin' ) );
	}

	public function test_cron_event_scheduled() {
		$this->assertFalse( wp_next_scheduled( 'digest_event' ) );

		digest()->activate_plugin();

		$this->assertInternalType( 'int', wp_next_scheduled( 'digest_event' ) );
	}

	public function test_cron_event_unscheduled() {
		digest()->activate_plugin();
		digest()->deactivate_plugin();

		$this->assertFalse( wp_next_scheduled( 'digest_event' ) );
	}

	public function test_send_email_empty_queue() {
		$action = new MockAction();

		add_filter( 'digest_cron_email_message', array( $action, 'filter' ) );
		digest()->send_email( 'Foo' );
		remove_filter( 'digest_cron_email_message', array( $action, 'filter' ) );

		$this->assertSame( 0, $action->get_call_count() );
	}

	public function test_send_email_non_empty_queue() {
		Queue::add( 'foo@example.com', 'foo', 'bar' );

		digest()->send_email( 'Foo' );

		/** @var MockPHPMailer $mailer */
		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertSame( 'Foo', $mailer->Subject );
	}

	public function test_send_email_non_empty_queue_registered_events() {
		Queue::add( 'foo@example.com', 'core_update_success', '100.1.0' );

		digest()->send_email( 'Foo' );

		/** @var MockPHPMailer $mailer */
		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertSame( 'Foo', $mailer->get_sent()->subject );
		$this->assertContains( 'Hi there', $mailer->get_sent()->body );
	}

	public function test_load_textdomain() {
		$action = new MockAction();

		add_action( 'load_textdomain', array( $action, 'action' ) );
		digest()->load_textdomain();
		remove_action( 'load_textdomain', array( $action, 'action' ) );

		$this->assertGreaterThan( 0, $action->get_call_count() );
	}

	public function test_event_registry() {
		$registry = new Registry();
		$plugin   = new \Required\Digest\Plugin( $registry );

		$this->assertSame( $registry, $plugin->event_registry() );
	}

	public function test_add_hooks() {
		$registry = new Registry();
		$plugin   = new \Required\Digest\Plugin( $registry );

		$plugin->add_hooks();

		$this->assertNotFalse(
			has_action( 'init', array(
				$plugin,
				'load_textdomain',
			) )
		);

		$this->assertNotFalse(
			has_action( 'comment_notification_recipients', array(
				$plugin,
				'comment_notification_recipients',
			) )
		);
		$this->assertNotFalse(
			has_action( 'comment_moderation_recipients', array(
				$plugin,
				'comment_moderation_recipients',
			) )
		);
		$this->assertNotFalse(
			has_action( 'auto_core_update_email', array(
				$plugin,
				'auto_core_update_email',
			) )
		);

		$this->assertNotFalse(
			has_action( 'init', array(
				$registry,
				'register_default_events',
			) )
		);
	}
}
