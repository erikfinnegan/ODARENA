@if($selectedDominion->isWatchingAnyDominion())
    <div class="box">
        <div class="box-header with-border">
            <h3 class="box-title"><i class="fas fa-eye"></i> Watched Dominions</h3>
        </div>
            <div class="box-body table-responsive no-padding">
                <table class="table">
                    <colgroup>
                        <col>
                        <col>
                        <col>
                        <col>
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Dominion</th>
                            <th>Land</th>
                            <th>Networth</th>
                            <th>Units<br>Returning</th>
                        </tr>
                    </thead>
                    <tbody>

                        @foreach($selectedDominion->watchedDominions()->get() as $watchedDominion)
                            <tr>
                                <td><a href="{{ route('dominion.insight.show', $watchedDominion) }}">{{ $watchedDominion->name }}</a></td>
                                <td>{{ number_format($landCalculator->getTotalLand($watchedDominion)) }}</td>
                                <td>{{ number_format($networthCalculator->getDominionNetworth($watchedDominion)) }}</td>
                                <td>
                                    @if ($militaryCalculator->hasReturningUnits($watchedDominion))
                                        <span class="label label-success">Yes</span>
                                    @else
                                        <span class="text-gray">No</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        <tr>
                            <td colspan="4">
                                <a href="{{ route('dominion.insight.watched-dominions') }}">View all watched dominions</a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
    </div>
@endif
