<?php namespace App\Http\Controllers\API;

use App\Domain;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Iodev\Whois\Exceptions\ConnectionException;
use Iodev\Whois\Exceptions\ServerMismatchException;
use Iodev\Whois\Exceptions\WhoisException;
use Iodev\Whois\Whois;

/**
 * Class DomainController
 * @package App\Http\Controllers\API
 */
class DomainController extends Controller
{

    const PENDING = 'pending';
    const ACTIVE = 'active';
    const INACTIVE = 'inactive';

    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     * @throws ConnectionException
     * @throws ServerMismatchException
     * @throws WhoisException
     */
    public function index()
    {
        $user = Auth::user();
        $alldomains = $user->domains->toArray();
        $domainlist = ['inactive'=>[],'active'=>[],'pending'=>[]];
        foreach ($alldomains as $domain) {
            $nameservers = $this->_checkDomain($domain['name']);
            $model = Domain::where('owner', $user->id)->where('name', $domain['name']);
            if ($nameservers == null) {
                $model->update(['status' => self::INACTIVE]);
                array_push($domainlist['inactive'], $domain['name']);
            } elseif (trim($nameservers[0][0]) == $user->ns1 && trim($nameservers[1][0]) == $user->ns2) {
                $model->update(['status' => self::ACTIVE]);
                array_push($domainlist['active'], $domain['name']);
            } else {
                $model->update(['status' => self::PENDING]);
                array_push($domainlist['pending'], $domain['name']);
            }
        }
        return response()->json(["user" => Auth::user(), "domains" => $domainlist]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return JsonResponse
     * @throws ConnectionException
     * @throws ServerMismatchException
     * @throws WhoisException
     */
    public function store(Request $request)
    {
        $owner = Auth::user();
        $owner_id = Auth::user()->id;
        $data = $request->all();
        $name = $data['name'];
        $validator = Validator::make($data, [
            'name' => [
                'required',
                Rule::unique('domains')->where(function ($query) use($name, $owner_id) {
                    return $query->where('name', $name)->where('owner', $owner_id);
                }),
                ],
            'value' => 'required|ip'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        } else {
            $input = $request->all();
            $newdomain = new Domain();
            $newdomain->name = $input['name'];
            $newdomain->value = $input['value'];
            $newdomain->owner = $owner_id;
            $nameservers = $this->_checkDomain($newdomain->name);
            if ($nameservers == null) {
                $newdomain->status = self::INACTIVE;
            } elseif (trim($nameservers[0][0]) == $owner->ns1 && trim($nameservers[1][0]) == $owner->ns2) {
                $newdomain->status = self::ACTIVE ;
            } else {
                $newdomain->status = self::PENDING;
            }

            $newdomain->save();

            return response()->json(['success' => $newdomain]);
        }
    }

    /**
     * @param $domain_name
     * @return mixed
     * @throws ConnectionException
     * @throws ServerMismatchException
     * @throws WhoisException
     * @throws Exception
     */
    function _checkDomain($domain_name) {
        try {
            $whois = Whois::create();
            $checkeddomain = $whois->lookupDomain($domain_name);
        }
        catch (ServerMismatchException $e) {
            throw new ServerMismatchException($e->getMessage());
        }

        preg_match_all('/nserver:(.+)/', $checkeddomain->getText(), $nameservers, PREG_OFFSET_CAPTURE);

        return $nameservers[1];
    }
}
