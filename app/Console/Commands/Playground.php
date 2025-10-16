<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class Playground extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:playground';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        for ($i = 0; $i < 100; $i++) {
            $word= fake()->paragraph;
            $this->info("askiing $word");
            try{
                $response = Http::timeout(6000)->post('https://api.africai.app/chat', [
                    'conversation_id' => '5ffe51b3-decf-4780-ab66-770489d067211688989340551',
                    'message' =>$word,
                    'user_id' => 'y8wigpNDmHdy13GcmoK2r5OgKSq2',
                ]);
                dump($response->body());
            }catch (\Exception $exception){
                $this->error($exception->getMessage());
            }
            sleep(10);
        }

    }

    function encrypt($data, $key)
    {
        $encrypted = openssl_encrypt($data, "aes-128-ecb", $key, 0,);
        return ($encrypted);
    }

    function decrypt($encrypted, $key)
    {
        $rawData = ($encrypted);
        $decrypted = openssl_decrypt($rawData, 'AES-128-ECB', $key, 1);
        $data = ($decrypted);
        return $data;
    }

    function padZero($data, $blocksize = 16)
    {
        $pad = $blocksize - (strlen($data) % $blocksize);
        return $data . str_repeat("\0", $pad);
    }

    function unpadZero($data)
    {
        return rtrim($data, "\0");
    }
}
