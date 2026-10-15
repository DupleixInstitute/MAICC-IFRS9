$u = App\Models\User::find(1);
$s = app('session')->driver(); $s->start();
$s->put(Auth::guard('web')->getName(), $u->id);
$s->put('password_hash_web', $u->getAuthPassword());
$s->save();
$name = config('session.cookie');
$val = Crypt::encrypt(Illuminate\Cookie\CookieValuePrefix::create($name, app('encrypter')->getKey()).$s->getId(), false);
file_put_contents(getenv('SP').'/cookie.json', json_encode(['name'=>$name,'value'=>$val,'id'=>$s->getId()]));
echo "ok";
