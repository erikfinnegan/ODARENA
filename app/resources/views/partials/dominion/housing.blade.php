@if($populationCalculator->getAvailableHousingFromWizardGuilds($selectedDominion) > 0)
<tr>
    <td><span data-toggle="tooltip" data-placement="top" title="Housing provided by Wizard Guilds or other buildings that house wizard units:<br>Filled / Available">Wizard housing:</span></td>
    <td>{{ number_format($populationCalculator->getUnitsHousedInWizardGuilds($selectedDominion)) }} / {{ number_format($populationCalculator->getAvailableHousingFromWizardGuilds($selectedDominion)) }}</td>
</tr>
@endif

@if($populationCalculator->getAvailableHousingFromForestHavens($selectedDominion) > 0)
<tr>
    <td><span data-toggle="tooltip" data-placement="top" title="Housing provided by Forest Havens or other buildings that house spy units:<br>Filled / Available">Spy housing:</span></td>
    <td>{{ number_format($populationCalculator->getUnitsHousedInForestHavens($selectedDominion)) }} / {{ number_format($populationCalculator->getAvailableHousingFromForestHavens($selectedDominion)) }}</td>
</tr>
@endif

@if($populationCalculator->getAvailableHousingFromUnitSpecificBuildings($selectedDominion) > 0)
<tr>
    <td><span data-toggle="tooltip" data-placement="top" title="Housing provided by buildings for specific units:<br>Filled / Available">Unit specific housing:</span></td>
    <td>{{ number_format($populationCalculator->getUnitsHousedInUnitSpecificBuildings($selectedDominion)) }} / {{ number_format($populationCalculator->getAvailableHousingFromUnitSpecificBuildings($selectedDominion)) }}</td>
</tr>
@endif

@if($populationCalculator->getAvailableHousingFromBarracks($selectedDominion) > 0)
<tr>
    <td><span data-toggle="tooltip" data-placement="top" title="Housing provided by barracks or other buildings and units that military units:<br>Filled / Available">Military housing:</span></td>
    <td>{{ number_format($populationCalculator->getUnitsHousedInBarracks($selectedDominion)) }} / {{ number_format($populationCalculator->getAvailableHousingFromBarracks($selectedDominion)) }}</td>
</tr>
@endif
