<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\ConnexionController;
use App\Http\Controllers\Auth\DeuxFacteursController;
use App\Http\Controllers\Public;
use App\Http\Controllers\Webhooks;
use App\Http\Middleware\VerifierJetonWebhook;
use Illuminate\Support\Facades\Route;

// --- Site public : aucune inscription, aucune connexion ---
Route::get('/', Public\AccueilController::class)->name('accueil');
Route::get('/signaler', [Public\SignalementController::class, 'create'])->name('signaler');
Route::post('/signaler', [Public\SignalementController::class, 'store'])->middleware('throttle:10,10')->name('signaler.store');
Route::get('/signaler/merci', [Public\SignalementController::class, 'merci'])->name('signaler.merci');
Route::get('/suivi', [Public\SuiviController::class, 'create'])->name('suivi');
Route::post('/suivi', [Public\SuiviController::class, 'show'])->middleware('throttle:20,10')->name('suivi.show');
Route::get('/parler', [Public\ChatController::class, 'page'])->name('chat');
Route::post('/parler/message', [Public\ChatController::class, 'message'])->middleware('throttle:60,1')->name('chat.message');
Route::get('/aide', [Public\PageController::class, 'aide'])->name('aide');
Route::get('/confidentialite', [Public\PageController::class, 'confidentialite'])->name('confidentialite');
Route::post('/langue/{langue}', [Public\PageController::class, 'langue'])->name('langue');
Route::get('/api/departements', [Public\PageController::class, 'departements']);
Route::get('/api/communes', [Public\PageController::class, 'communes']);

// --- Webhooks des canaux (jeton partagé obligatoire, sans session ni CSRF) ---
Route::prefix('webhooks')->middleware(['throttle:300,1', VerifierJetonWebhook::class])->group(function () {
    Route::match(['get', 'post'], '/sms', Webhooks\SmsController::class)->name('webhooks.sms');
    Route::post('/ussd', Webhooks\UssdController::class)->name('webhooks.ussd');
    Route::get('/whatsapp', [Webhooks\WhatsAppController::class, 'verifier']);
    Route::post('/whatsapp', [Webhooks\WhatsAppController::class, 'recevoir'])->name('webhooks.whatsapp');
    Route::post('/voix/message', [Webhooks\VoixController::class, 'message'])->name('webhooks.voix');
    Route::post('/voix/appel-manque', [Webhooks\VoixController::class, 'appelManque'])->name('webhooks.appel_manque');
});

// --- Espace des structures ---
Route::middleware('guest')->group(function () {
    Route::get('/espace/connexion', [ConnexionController::class, 'create'])->name('connexion');
    Route::post('/espace/connexion', [ConnexionController::class, 'store'])->middleware('throttle:5,1');
});

Route::middleware('auth')->prefix('espace')->group(function () {
    Route::post('/deconnexion', [ConnexionController::class, 'destroy'])->name('deconnexion');
    Route::get('/securite/activer', [DeuxFacteursController::class, 'activer'])->name('2fa.activer');
    Route::post('/securite/activer', [DeuxFacteursController::class, 'confirmer'])->middleware('hors_demo')->name('2fa.confirmer');
    Route::get('/securite/verifier', [DeuxFacteursController::class, 'verifier'])->name('2fa.verifier');
    Route::post('/securite/verifier', [DeuxFacteursController::class, 'valider'])->middleware('throttle:5,1')->name('2fa.valider');

    Route::middleware('deux_facteurs')->name('admin.')->group(function () {
        Route::get('/', Admin\TableauDeBordController::class)->name('tableau');
        Route::get('/signalements', [Admin\SignalementController::class, 'index'])->name('signalements');
        Route::get('/signalements/{signalement}', [Admin\SignalementController::class, 'show'])->name('signalements.show');
        Route::post('/signalements/{signalement}/orienter', [Admin\SignalementController::class, 'orienter'])->name('signalements.orienter');
        Route::post('/signalements/{signalement}/suivi', [Admin\SignalementController::class, 'suivre'])->name('signalements.suivre');
        Route::post('/signalements/{signalement}/contacter', [Admin\SignalementController::class, 'contacter'])->name('signalements.contacter');
        Route::post('/signalements/{signalement}/analyser', [Admin\IaController::class, 'analyser'])->middleware('throttle:10,1')->name('signalements.analyser');

        Route::middleware('role:admin,superviseur')->group(function () {
            Route::get('/simulateur', [Admin\SimulateurController::class, 'page'])->name('simulateur');
            Route::post('/ia/tester', [Admin\IaController::class, 'tester'])->middleware('throttle:5,1')->name('ia.tester');
            Route::post('/simulateur', [Admin\SimulateurController::class, 'envoyer'])->middleware('throttle:120,1')->name('simulateur.envoyer');
        });

        Route::middleware('role:admin')->group(function () {
            Route::get('/utilisateurs', [Admin\UtilisateurController::class, 'index'])->name('utilisateurs');
            Route::post('/utilisateurs', [Admin\UtilisateurController::class, 'store'])->middleware('hors_demo')->name('utilisateurs.store');
            Route::post('/utilisateurs/{user}/basculer', [Admin\UtilisateurController::class, 'basculer'])->middleware('hors_demo')->name('utilisateurs.basculer');
            Route::get('/journal', Admin\JournalController::class)->name('journal');
            Route::get('/structures', [Admin\StructureController::class, 'index'])->name('structures');
            Route::post('/structures', [Admin\StructureController::class, 'store'])->middleware('hors_demo')->name('structures.store');
            Route::post('/structures/{structure}/basculer', [Admin\StructureController::class, 'basculer'])->middleware('hors_demo')->name('structures.basculer');
        });
    });
});
