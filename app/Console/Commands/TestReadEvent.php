<?php

namespace App\Console\Commands;

use App\Jobs\ProcessReaderEvent;
use App\Models\Access\Card;
use App\Models\Hardware\Reader;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

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
