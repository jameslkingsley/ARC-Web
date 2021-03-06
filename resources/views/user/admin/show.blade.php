<script>
    $(document).ready(function(e) {
        $('#select_all').click(function(event) {
            $('#permission-form').find('.permission-item-check:not([data-disabled])').prop('checked', $(this).prop('checked'));
        });

        $('#permission-form').submit(function(event) {
            var form = $(this);

            $.ajax({
                type: 'POST',
                url: '{{ url('/hub/users') }}',
                data: form.serialize(),
                success: function(data) {
                    $('.user-permissions-modal').modal('hide');
                }
            });

            event.preventDefault();
        });

        $('#permission-form input[data-disabled="true"]').click(function(event) {
            event.preventDefault();
        });
    });
</script>

<div class="form-check">
    <label class="form-check-label">
        <input class="form-check-input" type="checkbox" name="select_all" id="select_all">
        Select All
    </label>
</div>

<hr>

<form id="permission-form">
    <input type="hidden" name="user_id" value="{{ $user->id }}">

    @foreach ($permissions as $permission)
        <div class="form-check {{ ($permission->name == 'users:all' && $user->hasPermission($permission->name)) ? 'disabled' : '' }}">
            <label class="form-check-label">
                <input
                    class="form-check-input permission-item-check"
                    type="checkbox"
                    name="{{ $permission->name }}"
                    value="1"
                    @if ($user->hasPermission($permission->name))
                        checked="true"
                    @endif

                    @if ($permission->name == 'users:all' && $user->hasPermission($permission->name))
                        data-disabled="true"
                    @endif>

                {{ $permission->name }}
            </label>
        </div>
    @endforeach
</form>
