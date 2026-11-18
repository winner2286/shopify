<ul class="plan-benefits">
    @foreach ($plan->includedFeatures() as $benefit)
        <li><x-icon name="check" :size="16" /> <span>{{ $benefit }}</span></li>
    @endforeach
</ul>
