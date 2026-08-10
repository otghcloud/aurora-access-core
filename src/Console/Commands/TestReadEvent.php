<?php

namespace OTGH\AccessControl\Core\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use OTGH\AccessControl\Core\Jobs\ProcessReaderEvent;
use OTGH\AccessControl\Core\Models\Access\Card;
use OTGH\AccessControl\Core\Models\Hardware\Reader;

#[Signature('app:test-read-event')]
#[Description('Command description')]
class TestReadEvent extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        //
        $this->info('Testing read event...');

        $cardNumber = '1286326';
        $readerIdentifier = 'ttyUSB1';

        // First, check for a valid access card
        $accessCard = Card::where('card_number', $cardNumber)->first();
        $accessReader = Reader::where('identifier', $readerIdentifier)->first();

        ProcessReaderEvent::dispatch($accessCard, $accessReader);

    }
}
