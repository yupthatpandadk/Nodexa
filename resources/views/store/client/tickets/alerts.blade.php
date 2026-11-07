@if($errors->any())
<div class="container ticketNotice" role="alert"><strong>Kontrollér oplysningerne</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
@if(session('success'))<div class="container ticketNotice" role="status">{{ session('success') }}</div>@endif
