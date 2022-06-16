<?php

namespace App\Repository\ModelInterfaces;

use App\Repositories\Generic\GenericInterface\GenericRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

interface LoginRepositoryInterface extends GenericRepositoryInterface
{
    function sendOtpCodeViaMail(Request $request);
    function resetPassword(Request $request);
    function updatePassword(Request $request);
    function newRegister(Request $request);
    function register(Request $request);
    function login();
    function logout(Request $request);
}
