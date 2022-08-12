@extends ('layouts.master')
@section('title', 'Advancements')

@section('content')
<div class="row">
    <div class="col-sm-12 col-md-9">

        <!-- RESOURCE -->
        <div class="row">
            <div class="col-md-12">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="ra ra-arson"></i> Operation</h3>
                    </div>
                    <div class="box-body">
                    {{-- //Columns must be a factor of 12 (1,2,3,4,6,12) --}}
                    @php
                        $numOfCols = 4;
                        $rowCount = 0;
                        $bootstrapColWidth = 12 / $numOfCols;
                    @endphp

                    <div class="row">

                    @foreach($advancements as $advancement)
                        @php
                            $dominionAdvancement = OpenDominion\Models\DominionAdvancement::where('advancement_id', $advancement->id)->where('dominion_id', $selectedDominion->id)->first();
                            $currentLevel = $advancementCalculator->getCurrentLevel($selectedDominion, $advancement);
                            $maxLevel = $advancementCalculator->getDominionMaxLevel($selectedDominion);
                            $progress = $currentLevel / $maxLevel;
                            $remaining = 1-$progress;
                            $progress *= 100;
                            $remaining *= 100;
                        @endphp
                        <div class="col-md-{{ $bootstrapColWidth }}">
                            <div class="box {{ $advancementCalculator->getCurrentLevel($selectedDominion, $advancement) ? 'box-success' : '' }}">
                                <div class="box-header with-border text-center">
                                    <h4 class="box-title">{{ $advancement->name }}</h4>
                                </div>
                                <div class="box-body">
                                    <div class="progress">
                                        @if($advancementCalculator->getCurrentLevel($selectedDominion, $advancement) == 0)
                                            <div class="progress-bar" role="progressbar" style="width: 100%" aria-valuenow="100" aria-valuemin="0" aria-valuemax="{{ $advancementCalculator->getDominionMaxLevel($selectedDominion) }}">No level</div>
                                        @else
                                            <div class="progress-bar label-success" role="progressbar" style="width: {{ $progress }}%" aria-valuenow="25" aria-valuemin="0" aria-valuemax="{{ $maxLevel }}">Level {{ $currentLevel }} </div>
                                            <div class="progress-bar label-warning" role="progressbar" style="width: {{ $remaining }}%" aria-valuenow="25" aria-valuemin="0" aria-valuemax="{{ $maxLevel }}"></div>
                                        @endif
                                        </div>
                                    <ul>
                                    </ul>
                                    <div class="box-footer text-center">
                                        <form action="{{ route('dominion.advancements2')}}" method="post" role="form" id="advancements_form">
                                            @csrf
                                            <input type="hidden" id="advancement_id" name="advancement_id" value="{{ $advancement->id }}" required>
                                            <button type="submit"
                                                    class="btn btn-primary btn-block"
                                                    {{ ($selectedDominion->isLocked() or !$advancementCalculator->canLevelUp($selectedDominion, $advancement)) ? 'disabled' : null }}
                                                    id="invade-button">
                                                    @if($advancementCalculator->canLevelUp($selectedDominion, $advancement))
                                                        <i class="fas fa-arrow-up"></i> Level up
                                                    @else
                                                        <i class="fas fa-check-circle"></i> Max level
                                                    @endif
                                            </button>
                                            @if($advancementCalculator->canLevelUp($selectedDominion, $advancement))
                                                <small class="text-muted">{{ number_format($advancementCalculator->getLevelUpCost($selectedDominion, $dominionAdvancement)) }} XP required</small>
                                            @endif
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        @php
                            $rowCount++;
                        @endphp

                        @if($rowCount % $numOfCols == 0)
                            </div><div class="row">
                        @endif

                    @endforeach
                    </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <div class="col-sm-12 col-md-3">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Information</h3>
            </div>
            <div class="box-body">
                <p>DESSY LEMON</p>
            </div>
        </div>
    </div>

</div>

@push('page-styles')
    <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/dataTables.bootstrap.css') }}">
@endpush

@push('page-scripts')
    <script type="text/javascript" src="{{ asset('assets/vendor/datatables/js/jquery.dataTables.js') }}"></script>
    <script type="text/javascript" src="{{ asset('assets/vendor/datatables/js/dataTables.bootstrap.js') }}"></script>
@endpush

@endsection

@push('page-styles')
    <link rel="stylesheet" href="{{ asset('assets/vendor/select2/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/admin-lte/plugins/bootstrap-slider/slider.css') }}">
@endpush

@push('page-scripts')
    <script type="text/javascript" src="{{ asset('assets/vendor/select2/js/select2.full.min.js') }}"></script>
    <script type="text/javascript" src="{{ asset('assets/vendor/admin-lte/plugins/bootstrap-slider/bootstrap-slider.js') }}"></script>
@endpush

