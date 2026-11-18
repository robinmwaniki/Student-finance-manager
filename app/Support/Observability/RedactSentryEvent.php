<?php

namespace App\Support\Observability;

use Sentry\Event;

/**
 * Removes high-risk request and user data before an event is sent to Sentry.
 */
final class RedactSentryEvent
{
    public static function handle(Event $event, mixed $hint = null): Event
    {
        $request = $event->getRequest();
        $safeRequest = [];

        if (isset($request['method']) && is_string($request['method'])) {
            $safeRequest['method'] = $request['method'];
        }

        if (isset($request['url']) && is_string($request['url'])) {
            // Query strings and fragments can contain tokens, account identifiers or form values.
            $parts = parse_url($request['url']);

            if (is_array($parts)) {
                $safeRequest['url'] = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? 'unknown')
                    .($parts['path'] ?? '/');
            }
        }

        $event->setRequest($safeRequest);
        $event->setUser(null);
        $event->setBreadcrumb([]);
        $event->setExtra(SensitiveDataRedactor::redactArray($event->getExtra()));

        foreach ($event->getContexts() as $name => $context) {
            $event->setContext($name, SensitiveDataRedactor::redactArray($context));
        }

        $event->setTags(SensitiveDataRedactor::redactArray($event->getTags()));

        if (is_string($event->getMessage())) {
            $event->setMessage(SensitiveDataRedactor::redactMessage($event->getMessage()));
        }

        return $event;
    }
}
