<?php

namespace App\Providers\Filament;

use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use pxlrbt\FilamentSpotlight\SpotlightPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->sidebarCollapsibleOnDesktop()
            // ->renderHook(
            //     \Filament\View\PanelsRenderHook::HEAD_END,
            //     fn (): string => '
            //         <style>
            //             aside.fi-sidebar {
            //                 transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1), min-width 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            //                 overflow-x: hidden !important;
            //             }
            //             .fi-main-ctn {
            //                 transition: padding-left 0.3s cubic-bezier(0.4, 0, 0.2, 1),
            //                             padding-right 0.3s cubic-bezier(0.4, 0, 0.2, 1),
            //                             padding-inline-start 0.3s cubic-bezier(0.4, 0, 0.2, 1),
            //                             padding-inline-end 0.3s cubic-bezier(0.4, 0, 0.2, 1),
            //                             margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1),
            //                             margin-right 0.3s cubic-bezier(0.4, 0, 0.2, 1),
            //                             margin-inline-start 0.3s cubic-bezier(0.4, 0, 0.2, 1),
            //                             margin-inline-end 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            //             }
            //         </style>
            //     '
            // )
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
                SpotlightPlugin::make(),
            ]);
    }
}
