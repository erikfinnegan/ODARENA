@extends('layouts.master')

@section('page-header', 'Daily Bonus')

@section('content')
    <div class="row">

        <div class="col-sm-12 col-md-9">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-plus"></i> Daily Bonus</h3>
                </div>
                <div class="box-body">
                    <div class="row">
                        <div class="col-xs-6 text-center">
                            <form action="{{ route('dominion.bonuses.land') }}" method="post" role="form">
                                @csrf
                                <button type="submit" name="land" class="btn btn-primary btn-lg" {{ $selectedDominion->isLocked() || $selectedDominion->daily_land || $selectedDominion->protection_ticks > 0 || !$selectedDominion->round->hasStarted() ? 'disabled' : null }}>
                                    <i class="ra scroll-unfurled ra-lg"></i>
                                    Claim Daily Land Bonus
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="box">
                <div class="box-body">
                    <p>While you're here, consider supporting the project in one (or more) of the following ways:</p>

                    <div class="row">
                        @if ($discordInviteLink = config('app.discord_invite_link'))
                            <div class="col-md-4 text-center">
                                <h4>Join the chat</h4>
                                <p>
                                    <a href="{{ $discordInviteLink }}" target="_blank">
                                        <img src="{{ asset('assets/app/images/join-the-discord.png') }}" alt="Join the Discord" class="img-responsive" style="max-width: 200px; margin: 0 auto;">
                                    </a>
                                </p>
                                <p>Discord is a chat program that I use for OpenDominion's game announcements, its development, and generic banter with other players and people interested in the project.</p>
                                <p>Feel free to join us and chat along!</p>
                            </div>
                        @endif

                        <div class="col-md-4 text-center">
                            <h4>Rate on PBBG.com</h4>
                            <p><a href="https://pbbg.com" target="_blank">PBBG.com</a> is a directory listing of Persistent Browser-Based Games (PBBG) such as OD Arena.</p>
                            <p>Consider <a href="https://pbbg.com/games/odarena" target="_blank">rating the project on PBBG.com</a> and share your experience with it, to help new players find the game!</p>
                        </div>

                        @if ($patreonPledgeLink = config('app.patreon_pledge_link'))
                            <div class="col-md-4 text-center">
                                <h4>Become a Patron</h4>
                                <p>
                                    <a href="{{ $patreonPledgeLink }}" data-patreon-widget-type="become-patron-button">Become a Patron!</a>
                                </p>
                                <p>OpenDominion is (and always will be) fully free to play, with no advertisements, micro-transactions, lootboxes, premium currencies, or paid DLCs.</p>
                                <p>I've put in a lot of effort into OpenDominion over the past six years, and I've been paying everything I needed to help me build and run OD out of my own pocket. Financial support through Patreon (even a single dollar) is therefore most welcome!</p>
                                <p>(Because of my strict 'no-P2W'-policy, no in-game benefits will be given to donators over regular players. You will get a spiffy color in the Discord, though!)</p>
                            </div>
                        @endif
                    </div>
                </div>
                <div class="box-footer">
                    <p>Thank you for your attention, and please enjoy playing OD Arena!</p>
                </div>
            </div>
        </div>

        <div class="col-sm-12 col-md-3">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">Information</h3>
                </div>
                <div class="box-body">
                    <p>The Daily Land Bonus instantly gives you some barren acres of {{ str_plural($selectedDominion->race->home_land_type) }}. You have a 0.50% chance to get 100 acres, otherwise you get a random amount between 10 and 40 acres</p>
                </div>
            </div>
        </div>
    </div>
@endsection

@if (config('app.patreon_pledge_link'))
    @push('page-scripts')
        <script async src="https://c6.patreon.com/becomePatronButton.bundle.js"></script>
    @endpush

    @push('inline-styles')
        <style type="text/css">
            .patreon-widget {
                width: 176px !important;
            }
        </style>
    @endpush
@endif
