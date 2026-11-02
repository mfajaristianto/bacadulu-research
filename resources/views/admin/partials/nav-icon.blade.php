@switch($icon)

    @case('overview')
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.7"
        >
            <path d="M4 4h6v6H4z"/>
            <path d="M14 4h6v6h-6z"/>
            <path d="M4 14h6v6H4z"/>
            <path d="M14 14h6v6h-6z"/>
        </svg>
        @break


    @case('library')
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.7"
        >
            <path d="M5 4h11a3 3 0 0 1 3 3v13H8a3 3 0 0 1-3-3z"/>
            <path d="M8 4v16"/>
        </svg>
        @break


    @case('category')
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.7"
        >
            <path d="M4 5h6v6H4z"/>
            <path d="M14 5h6v6h-6z"/>
            <path d="M4 15h6v5H4z"/>
            <path d="M14 15h6v5h-6z"/>
        </svg>
        @break


    @case('collection')
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.7"
        >
            <path d="M4 7h16"/>
            <path d="M6 4h12"/>
            <rect
                x="3"
                y="10"
                width="18"
                height="10"
                rx="2"
            />
        </svg>
        @break


    @case('sync')
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.7"
        >
            <path d="M20 7h-5V2"/>
            <path d="M20 7a8 8 0 0 0-13.7-2.7L4 6.6"/>

            <path d="M4 17h5v5"/>
            <path d="M4 17a8 8 0 0 0 13.7 2.7l2.3-2.3"/>
        </svg>
        @break


    @case('tools')
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.7"
        >
            <path d="M12 3v18"/>
            <path d="M3 12h18"/>
            <circle
                cx="12"
                cy="12"
                r="8"
            />
        </svg>
        @break


    @case('prompt')
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.7"
        >
            <path d="M5 4h14v16H5z"/>
            <path d="M8 8h8"/>
            <path d="M8 12h8"/>
            <path d="M8 16h5"/>
        </svg>
        @break


    @case('runs')
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.7"
        >
            <path d="M5 18V8"/>
            <path d="M10 18V4"/>
            <path d="M15 18v-6"/>
            <path d="M20 18V9"/>
        </svg>
        @break


    @case('users')
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.7"
        >
            <circle
                cx="9"
                cy="8"
                r="3"
            />

            <path d="M3 19c0-4 2.5-7 6-7s6 3 6 7"/>
            <path d="M16 7a3 3 0 0 1 0 6"/>
            <path d="M18 14c2 .8 3 2.5 3 5"/>
        </svg>
        @break


    @case('audit')
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.7"
        >
            <path d="M5 3h14v18H5z"/>
            <path d="M8 8h8"/>
            <path d="M8 12h8"/>
            <path d="M8 16h5"/>
        </svg>
        @break


    @case('security')
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.7"
        >
            <path d="M12 3l7 3v5c0 5-3 8-7 10-4-2-7-5-7-10V6z"/>
            <path d="M9 12l2 2 4-4"/>
        </svg>
        @break
        @case('glossary')

    <svg
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.7"
    >
        <path d="M5 4h11a3 3 0 0 1 3 3v13H8a3 3 0 0 1-3-3z"/>
        <path d="M8 4v16"/>
        <path d="M11 9h5"/>
        <path d="M11 13h4"/>
    </svg>

    @break


@case('template')

    <svg
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.7"
    >
        <rect
            x="4"
            y="3"
            width="16"
            height="18"
            rx="2"
        />

        <path d="M8 7h8"/>
        <path d="M8 11h8"/>
        <path d="M8 15h5"/>
        <path d="M8 18h8"/>
    </svg>

    @break


    @case('settings')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 7h10M18 7h2M4 17h4M12 17h8"/>
            <circle cx="16" cy="7" r="2"/>
            <circle cx="10" cy="17" r="2"/>
        </svg>
        @break

    @case('versions')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 3 3 8l9 5 9-5-9-5z"/>
            <path d="m3 13 9 5 9-5"/>
        </svg>
        @break

    @default
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.7"
        >
            <circle
                cx="12"
                cy="12"
                r="8"
            />

            <path d="M12 8v4"/>
            <path d="M12 16h.01"/>
        </svg>

@endswitch