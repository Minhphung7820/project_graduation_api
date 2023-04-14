<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerM;
use App\Models\RatingP;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;

Carbon::setLocale('vi');
class CustomerController extends Controller
{
    public function _login(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required'
        ], [
            'email.required' => 'Vui lòng nhập Email !',
            'email.email' => 'Email sai định dạng !',
            'password.required' => 'Vui lòng nhập mật khẩu !'
        ]);
        if ($validation->fails()) {
            return response()->json(['status' => 202, 'msg' => $validation->errors()]);
        }

        if (auth()->guard('customer')->attempt(['email' => $request->email, 'password' => $request->password, 'active' => 1, 'provider_id' => null, 'provider' => null])) {
            $customer = CustomerM::where('email', '=', $request->email)->whereNull('provider')->whereNull('provider_id')->where('active', '=', 1)->first();
            return response()->json([
                'status' => 200,
                'msg' => 'Đăng nhập thành công !',
                'token' => $customer->createToken("API TOKEN")->plainTextToken
            ]);
        } else {
            return response()->json([
                'status' => 204,
                'msg' => 'Email hoặc mật khẩu không đúng !',
            ]);
        }
    }

    public function _register(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'name' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|regex:/^.*(?=.{3,})(?=.*[a-zA-Z])(?=.*[0-9])(?=.*[\d\x]).*$/',
            'phone' => 'required|numeric',
            'confirm_password' => 'required|same:password'
        ], [
            'name.required' => 'Vui lòng nhập họ tên !',
            'email.required' => 'Vui lòng nhập Email !',
            'email.email' => 'Email sai định dạng !',
            'password.required' => 'Vui lòng nhập mật khẩu !',
            'password.min' => 'Mật khẩu yếu !',
            'password.regex' => 'Mật khẩu yếu !',
            'confirm_password.required' => 'Vui lòng nhập lại mật khẩu !',
            'confirm_password.same' => 'Nhập lại mật khẩu không đúng !',
            'phone.required' => 'Vui lòng nhập số điện thoại !',
            'phone.numeric' => 'Số điện thoại sai định dạng !'
        ]);
        if ($validation->fails()) {
            return response()->json(['status' => 202, 'msg' => $validation->errors()]);
        } else {
            $checkEmailExists = CustomerM::where('email', '=', trim($request->email))->whereNull('provider')->whereNull('provider_id')->count();
            if ($checkEmailExists > 0) {
                return response()->json(['status' => 204, 'msg' => 'Email đã tồn tại hãy chọn Email khác !']);
            } else {
                $insert  = CustomerM::create([
                    'name' => trim(strip_tags(ucwords($request->name))),
                    'email' => trim(strip_tags($request->email)),
                    'phone' => trim(strip_tags($request->phone)),
                    'password' => bcrypt($request->password),
                    'hash_email_active' => md5(trim($request->email)),
                    'created_at' => now()
                ]);
                if ($insert) {
                    $data = array(
                        'url' => 'http://127.0.0.1:8000/active/account/email-verify/' . md5(trim($request->email)) . '.html',
                    );
                    $send = Mail::to($request->email)->send(new \App\Mail\ActiveAccount($data));
                    if ($send) {
                        return response()->json(['status' => 200, 'msg' => 'Đã đăng ký thành công , vui lòng kiểm tra email để kích hoạt tài khoản !', 'hash_email' => md5(trim($request->email))]);
                    }
                }
            }
        }
    }

    public function active(Request $request)
    {
        $check = CustomerM::where('hash_email_active', '=', $request->hash_email)->whereNull('provider')->whereNull('provider_id')->first();
        if ($check) {
            $check->update([
                'active' => 1
            ]);
            auth()->guard('customer')->login($check);
            return response()->json([
                'status' => 200,
                'msg' => 'Đăng nhập thành công !',
                'token' => $check->createToken("API TOKEN")->plainTextToken
            ]);
        } else {
            return response()->json([
                'status' => 404,
            ]);
        }
    }

    public function logout()
    {
        auth()->guard('customer')->logout();
        return response()->json(['status' => 200]);
    }
    public function seenNotication(Request $request)
    {
        RatingP::where("idCustomer", '=', $request->id)->where('seen', '=', 'NOT_SEEN')->update([
            'seen' => 'SEEN',
        ]);
        return response()->json(['Đã xem thông báo !']);
    }
    public function loadNotificationUser($id = null)
    {

        $op_not_seen = '';
        $op_seen = '';
        $not_seen = RatingP::with('customer', 'product')->where('seen', '=', 'NOT_SEEN')->where('status', '!=', 1)->where("idCustomer", "=", $id)->orderBy('updated_at', 'desc')->orderBy('id', 'desc')->get();
        $count_not_seen = $not_seen->count();
        $seen = RatingP::with('customer', 'product')->where('seen', '=', 'SEEN')->where('status', '!=', 1)->where("idCustomer", "=", $id)->orderBy('updated_at', 'desc')->orderBy('id', 'desc')->get();
        // =========================
        if ($count_not_seen > 0) {
            foreach ($not_seen as $key => $value) {
                if ($value->status == 2) {
                    $stt = 'đã được duyệt';
                    $alerts = 'success';
                } else if ($value->status == 3) {
                    $stt = 'đã bị spam';
                    $alerts = 'danger';
                }
                $op_not_seen .= ' <div class="alert alert-' . $alerts . '" role="alert">
                                   Đánh giá ';
                for ($i = 1; $i <= 5; $i++) {
                    if ($value->num_star >= $i) {
                        $class_name = 'text-warning';
                    } else {
                        $class_name = 'star-light';
                    }
                    $op_not_seen .= ' <i class="fas fa-star ' . $class_name . ' mr-1"></i>';
                }

                $op_not_seen .= '
                <em style="font-weight:bold;">' . $value->content_review . '</em> về sản phẩm <strong>' . $value->product->name . '</strong> ' . $stt . ' lúc ' . Carbon::parse($value->updated_at)->diffForHumans() . '
                                  </div>';
            }
        } else {
            $op_not_seen .= ' <h3>Không có thông báo nào gần đây !</h3>';
        }
        // ==========================
        if (count($seen) > 0) {
            foreach ($seen as $key => $value) {
                if ($value->status == 2) {
                    $stt = 'đã được duyệt';
                } else if ($value->status == 3) {
                    $stt = 'đã bị spam';
                }
                $op_seen  .= ' <div class="alert alert-light" role="alert">
                                   Đánh giá ';
                for ($i = 1; $i <= 5; $i++) {
                    if ($value->num_star >= $i) {
                        $class_name = 'text-warning';
                    } else {
                        $class_name = 'star-light';
                    }
                    $op_seen  .= ' <i class="fas fa-star ' . $class_name . ' mr-1"></i>';
                }

                $op_seen  .= '
                <em style="font-weight:bold;">' . $value->content_review . '</em> về sản phẩm <strong>' . $value->product->name . '</strong> ' . $stt . ' lúc ' . Carbon::parse($value->updated_at)->diffForHumans() . '
                                  </div> <hr>';
            }
        } else {
            $op_seen  .= ' <h3>Không có thông báo nào gần đây !</h3>';
        }

        return response()->json(['not_seen' => $op_not_seen, 'count' => $count_not_seen, 'seen' => $op_seen]);
    }
    public function change_password(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'old_password' => 'required',
            'new_password' => 'required|min:8|regex:/^.*(?=.{3,})(?=.*[a-zA-Z])(?=.*[0-9])(?=.*[\d\x]).*$/',
            'confirm_password' => 'required|same:new_password',
        ], [
            'old_password.required' => 'Vui lòng nhập mật khẩu cũ !',
            'new_password.required' => 'Vui lòng nhập mật khẩu mới |',
            'new_password.min' => 'Mật khẩu yếu !',
            'new_password.regex' => 'Mật khẩu yếu !',
            'confirm_password.required' => 'Vui lòng nhập lại mật khẩu !',
            'confirm_password.same' => 'Nhập lại mật khẩu không đúng !',
        ]);
        if ($validation->fails()) {
            return response()->json(['status' => 202, 'msg' => $validation->errors()]);
        } else {
            $user = CustomerM::find($request->idCus);
            if (Hash::check($request->old_password,  $user->password)) {
                $user->update([
                    'password' => bcrypt($request->new_password),
                ]);
                return response()->json(['status' => 200, 'msg' => 'Thay đổi mật khẩu thành công , mời bạn tiến hành đăng nhập lại !']);
            } else {
                return response()->json(['status' => 204, 'msg' => 'Mật khẩu cũ không chính xác !']);
            }
        }
    }

    public function forgot(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'email_forgot' => 'required|email',
        ], [
            'email_forgot.required' => 'Vui lòng nhập email !',
            'email_forgot.email' => 'Email sai định dạng !'
        ]);
        if ($validation->fails()) {
            return response()->json(['status' => 202, 'msg' => $validation->errors()]);
        } else {
            $check  = CustomerM::where('email', '=', trim($request->email_forgot))->whereNull('provider')->whereNull('provider_id')->count();
            if ($check > 0) {
                $detail = array(
                    'url' => 'http://127.0.0.1:8000/customer/reset-password/' . md5(trim($request->email_forgot)) . '.html',
                );
                $send = Mail::to($request->email_forgot)->send(new \App\Mail\ResetPassword($detail));
                if ($send) {
                    return response()->json(['status' => 200, 'msg' => 'Đã gửi email thành công , quý khách vui lòng kiểm tra email để khôi phục mật khẩu !']);
                }
            } else {
                return response()->json(['status' => 204, 'msg' => 'Email này chưa được đăng ký hoặc không tồn tại !']);
            }
        }
    }

    public function reset_password(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'password_reset_new' => 'required|min:8|regex:/^.*(?=.{3,})(?=.*[a-zA-Z])(?=.*[0-9])(?=.*[\d\x]).*$/',
            'password_reset_confirm' => 'required|same:password_reset_new'
        ], [
            'password_reset_new.required' => 'Vui lòng nhập mật khẩu !',
            'password_reset_new.min' => 'Mật khẩu yếu !',
            'password_reset_new.regex' => 'Mật khẩu yếu !',
            'password_reset_confirm.required' => 'Vui lòng nhập lại mật khẩu !',
            'password_reset_confirm.same' => 'Nhập lại mật khẩu không đúng !',
        ]);

        if ($validation->fails()) {
            return response()->json(['status' => 202, 'msg' => $validation->errors()]);
        } else {
            $check  = CustomerM::where('hash_email_active', '=', $request->hashE)->whereNull('provider')->whereNull('provider_id')->first();
            if ($check) {
                $check->update([
                    'password' => bcrypt($request->password_reset_new),
                ]);
                return response()->json(['status' => 200, 'msg' => 'Đã tạo mới mật khẩu mời quý khách đăng nhập lại !']);
            } else {
                return response()->json(['status' => 404, 'msg' => 'Không tìm thấy tài khoản !']);
            }
        }
    }
}
