<?php namespace App\Http\Controllers\API;

use App\Domain;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Iodev\Whois\Whois;

/**
 * Class DomainController
 * @package App\Http\Controllers\API
 */
class DomainController extends Controller
{
    /**
     * @throws \Iodev\Whois\Exceptions\ConnectionException
     * @throws \Iodev\Whois\Exceptions\ServerMismatchException
     * @throws \Iodev\Whois\Exceptions\WhoisException
     */
    public function index(Request $request) {

        $whois = Whois::create();
        dd($request->all());
        $domain = $whois->lookupDomain("yekitaapp.com");
        preg_match_all('/Name Server:(.+)/', $domain->getText(), $nameservers, PREG_OFFSET_CAPTURE);
        $domain = Domain::create(['name' => $domain->getDomain(), 'owner' => $request->user(), 'ns1' => $nameservers[0],
            'ns2' => $nameservers[1]]);

        return response()->json(['result' => $domain]);
    }
}
