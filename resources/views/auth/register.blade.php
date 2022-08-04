<script type="text/javascript" src="{{ asset('js/security.js') }}"></script>
<script>
    /**
     * Generate a pair keys for encryption and another one for singing message on Register page load.
     */
    (async function() {
        await generateKeyPairs();
    })();
</script>
<x-guest-layout>
    <x-auth-card>
        <x-slot name="logo">
            <a href="/">
                <x-application-logo class="w-20 h-20 fill-current text-gray-500" />
            </a>
        </x-slot>

        <!-- Validation Errors -->
        <x-auth-validation-errors class="mb-4" :errors="$errors" />

        <form id="registerForm" method="POST" action="{{ route('register') }}">
            @csrf

            <!-- Name -->
            <div>
                <x-label for="name" :value="__('Name')" />

                <x-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required
                    autofocus />
            </div>

            <!-- Email Address -->
            <div class="mt-4">
                <x-label for="email" :value="__('Email')" />

                <x-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required />
            </div>

            <!-- Password -->
            <div class="mt-4">
                <x-label for="password" :value="__('Password')" />

                <x-input id="password" class="block mt-1 w-full" type="password" name="password" required
                    autocomplete="new-password" />
            </div>

            <!-- Confirm Password -->
            <div class="mt-4">
                <x-label for="password_confirmation" :value="__('Confirm Password')" />

                <x-input id="password_confirmation" class="block mt-1 w-full" type="password"
                    name="password_confirmation" required />
            </div>


            <!-- public key -->
            <!-- generate key pair -->


            <input id="inputPublicKeyEncryption" type="hidden" name="public_key_encryption">
            <input id="inputPublicKeySignature" type="hidden" name="public_key_signature">


            <div class="flex items-center justify-end mt-4">
                <a class="underline text-sm text-gray-600 hover:text-gray-900" href="{{ route('login') }}">
                    {{ __('Already registered?') }}
                </a>

                <x-button id="registerSubmit" class="ml-4">
                    {{ __('Register') }}
                </x-button>
            </div>
        </form>

        <script>
            /**
             * function used to retrieve the previously generated public keys and send submit them to the server 
             */
            $("#registerSubmit").click(function() {
                console.log(localStorage.getItem("public_key_encryption"));
                console.log(localStorage.getItem("public_key_signature"));

                $("#inputPublicKeyEncryption").val(localStorage.getItem("public_key_encryption"));
                $("#inputPublicKeySignature").val(localStorage.getItem("public_key_signature"));
                $("#registerForm").submit();
            });
        </script>
    </x-auth-card>
</x-guest-layout>
