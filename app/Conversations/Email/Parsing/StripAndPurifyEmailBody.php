<?php

namespace App\Conversations\Email\Parsing;

use App\Conversations\Messages\MessageBodyPurifier;
use Common\AI\Llm;
use Common\AI\Providers\ProviderParams;
use EmailReplyParser\Parser\EmailParser;
use Illuminate\Support\Facades\View;
use Throwable;

class StripAndPurifyEmailBody
{
    public function execute(string $body, string $type): string|null
    {
        // this seems to cause encoding issues
        $body = str_replace('charset=Windows-1252', 'charset=UTF-8', $body);

        $processedBody = null;

        // fallback to local parsers if openai fails
        if (config('services.openai.api_key')) {
            try {
                $processedBody = $this->usingOpenAi($body, $type);
            } catch (Throwable $e) {
                report($e);
            }
        }

        if (!$processedBody) {
            $processedBody = $this->usingLocalParsers($body, $type);
        }
        return $processedBody;
    }

    protected function usingOpenAi(string $body, string $type): string|null
    {
        // run through common quoted text and signature strippers, incase llm missed some
        if ($type === 'html') {
            $strippedBody = $this->stripCommonQuotedTextAndSignature($body);
            $body = (new MessageBodyPurifier())->emailBody($strippedBody);
        } else {
            $body = (new EmailParser())->parse($body)->getVisibleText();
            $body = (new MessageBodyPurifier())->plainEmailBody($body);
        }

        $prompt = View::make('tickets.clean-email-body-prompt', [
            'email' => $body,
        ])->render();

        $strippedBody = Llm::resolveProvider(
            new ProviderParams(prompt: $prompt, temperature: 0),
        )->generateText()->output;

        return html_entity_decode($strippedBody);
    }

    protected function usingLocalParsers(
        string $body,
        string $type,
    ): string|null {
        if ($type === 'plain') {
            $body = (new EmailParser())->parse($body)->getVisibleText();
            return (new MessageBodyPurifier())->plainEmailBody($body);
        }

        // this uses html classes and tags mostly so it's not suitable for plain text emails
        $strippedHtml = $this->stripCommonQuotedTextAndSignature($body);

        // this will turn double newlines into <p> tags in plain text emails
        return (new MessageBodyPurifier())->emailBody($strippedHtml);
    }

    protected function stripCommonQuotedTextAndSignature(string $body): string
    {
        $body = (new StripQuotedEmailText())->execute($body);
        return (new StripEmailSignature())->execute($body);
    }
}
