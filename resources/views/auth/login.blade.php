@extends('layouts.app')
@push('styles')
<style>
	.login {
		min-height: 100vh;
		display: grid;
		grid-template-columns: 1.1fr .9fr;
		background: #fff7f0;
	}

	.login-art {
		padding: clamp(30px, 7vw, 100px);
		color: #fff;
		background: linear-gradient(145deg, #c95a1d, #f59e0b);
		position: relative;
		overflow: hidden;
	}

	.login-art:after {
		content: '';
		position: absolute;
		width: 480px;
		height: 480px;
		border: 1px solid #ffffff35;
		border-radius: 50%;
		right: -180px;
		bottom: -130px;
		box-shadow: 0 0 0 50px #ffffff0c, 0 0 0 100px #ffffff08;
	}

	.login-art h1 {
		font-size: clamp(42px, 6vw, 78px);
		line-height: .95;
		max-width: 560px;
		margin-top: 30px;
	}

	.login-art p {
		max-width: 390px;
		color: #fff1de;
		line-height: 1.7;
	}

	.login-panel {
		background: var(--paper);
		display: grid;
		place-items: center;
		padding: 25px;
	}

	.login-card {
		width: min(420px, 100%);
	}

	.login-card h2 {
		font-size: 32px;
	}

	.login-art-meta {
		margin-top: 80px;
		color: #ffe9d3;
		font-size: 13px;
	}
	
	.eyebrow {
		color: #c95a1d;
	}

	.btn {
		background: linear-gradient(135deg, #f97316, #ea580c);
		color: #fff;
		box-shadow: 0 10px 22px rgba(249, 115, 22, 0.24);
	}

	.login-remember {
		display: flex;
		align-items: center;
		gap: 8px;
		font-weight: 400;
	}

	.login-remember input {
		width: auto;
	}

	.login-note {
		font-size: 12px;
		margin-top: 16px;
	}

	@media (max-width: 850px) {
		.login {
			grid-template-columns: 1fr;
		}

		.login-art {
			min-height: 300px;
			padding: 36px 24px;
		}

		.login-art h1 {
			font-size: 48px;
		}

		.login-panel {
			padding: 28px 18px;
		}
	}

	@media (max-width: 600px) {
		.login-art {
			min-height: 0;
			padding: 24px 20px 20px;
		}

		.login-art h1 {
			margin-top: 22px;
			font-size: clamp(38px, 11vw, 46px);
		}

		.login-art p {
			margin-bottom: 0;
		}

		.login-art-meta {
			margin-top: 24px;
			font-size: 11px;
		}

		.login-panel {
			place-items: start center;
			padding: 26px 18px 32px;
		}

		.login-card h2 {
			font-size: 28px;
		}
	}
</style>
@endpush
@section('content')
<div class="login"><section class="login-art"><div class="brand">EKLESIA GROUP<small>STAFF ATTENDANCE SYSTEM</small></div><h1>Datang tepat.<br>Kerja berdampak.</h1><p>Satu ruang untuk absensi tim yang lebih tertib, terukur, dan tetap manusiawi.</p><div class="login-art-meta">ABSENSI DIGITAL · FACE CHECK · LOCATION CHECK</div></section><section class="login-panel"><form class="login-card form-grid" method="post" action="{{ route('login.authenticate') }}">@csrf<div class="eyebrow">Portal internal</div><h2>Masuk ke akun</h2><p class="muted">Gunakan username yang diberikan admin untuk melanjutkan.</p>@if(session('error'))<div class="alert error">{{ session('error') }}</div>@endif<label>Username<input name="username" value="{{ old('username') }}" autocomplete="username" required autofocus></label><label>Password<input type="password" name="password" autocomplete="current-password" required></label><label class="login-remember"><input type="checkbox" name="remember"> Ingat perangkat ini</label><button class="btn" type="submit">Masuk ke dashboard →</button><p class="muted login-note">Akses admin dan staff menggunakan satu pintu yang sama.</p></form></section></div>
@endsection
