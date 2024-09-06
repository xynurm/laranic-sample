<?php

namespace App\Http\Controllers;

use App\Services\OAuth2ClientService;
use Illuminate\Http\Request;

class TesController extends Controller
{
    protected $oauth2Client;

    public function __construct(OAuth2ClientService $oauth2Client)
    {
        $this->oauth2Client = $oauth2Client;
    }

    public function index()
    {
        return $this->oauth2Client;
    }
}
