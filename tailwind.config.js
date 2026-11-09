/** @type {import('tailwindcss').Config} */
module.exports = {
  darkMode: ["class"],
  // Admin UI only (the public widget uses its own CSS in a Shadow DOM).
  content: ["./src/admin/**/*.{js,ts,jsx,tsx}", "./src/components/**/*.{js,ts,jsx,tsx}"],
  prefix: "",
  // Scope utilities to our app root and skip the global reset so the
  // surrounding WP admin chrome is untouched.
  important: "#terax-ai-admin",
  corePlugins: { preflight: false },
  theme: {
  	container: {
  		center: true,
  		padding: '2rem',
  		screens: {
  			'2xl': '1400px'
  		}
  	},
  	extend: {
  		colors: {
  			border: 'hsl(var(--border))',
  			input: 'hsl(var(--input))',
  			ring: 'hsl(var(--ring))',
  			background: 'hsl(var(--background))',
  			foreground: 'hsl(var(--foreground))',
  			primary: {
  				DEFAULT: 'hsl(var(--primary))',
  				foreground: 'hsl(var(--primary-foreground))'
  			},
  			secondary: {
  				DEFAULT: 'hsl(var(--secondary))',
  				foreground: 'hsl(var(--secondary-foreground))'
  			},
  			destructive: {
  				DEFAULT: 'hsl(var(--destructive))',
  				foreground: 'hsl(var(--destructive-foreground))'
  			},
  			muted: {
  				DEFAULT: 'hsl(var(--muted))',
  				foreground: 'hsl(var(--muted-foreground))'
  			},
  			accent: {
  				DEFAULT: 'hsl(var(--accent))',
  				foreground: 'hsl(var(--accent-foreground))'
  			},
  			popover: {
  				DEFAULT: 'hsl(var(--popover))',
  				foreground: 'hsl(var(--popover-foreground))'
  			},
  			card: {
  				DEFAULT: 'hsl(var(--card))',
  				foreground: 'hsl(var(--card-foreground))'
  			},
  			chart: {
  				'1': 'hsl(var(--chart-1))',
  				'2': 'hsl(var(--chart-2))',
  				'3': 'hsl(var(--chart-3))',
  				'4': 'hsl(var(--chart-4))',
  				'5': 'hsl(var(--chart-5))'
  			}
  		},
  		borderRadius: {
  			lg: 'var(--radius)',
  			md: 'calc(var(--radius) - 2px)',
  			sm: 'calc(var(--radius) - 4px)'
  		},
  		keyframes: {
  			'accordion-down': {
  				from: {
  					height: '0'
  				},
  				to: {
  					height: 'var(--radix-accordion-content-height)'
  				}
  			},
  			'accordion-up': {
  				from: {
  					height: 'var(--radix-accordion-content-height)'
  				},
  				to: {
  					height: '0'
  				}
  			},
  			'terax-shine': {
  				'0%': { transform: 'translateX(-120%) skewX(-20deg)' },
  				'60%, 100%': { transform: 'translateX(500%) skewX(-20deg)' }
  			},
  			'terax-hop': {
  				'0%, 100%': { transform: 'translateY(0)' },
  				'50%': { transform: 'translateY(-2px)' }
  			},
  			'terax-wiggle': {
  				'0%, 60%, 100%': { transform: 'rotate(0deg) scale(1)' },
  				'68%': { transform: 'rotate(-6deg) scale(1.08)' },
  				'76%': { transform: 'rotate(6deg) scale(1.08)' },
  				'84%': { transform: 'rotate(-4deg) scale(1.08)' },
  				'92%': { transform: 'rotate(3deg) scale(1.04)' }
  			},
  			'terax-sheen': {
  				'0%': { backgroundPosition: '0% 50%' },
  				'100%': { backgroundPosition: '200% 50%' }
  			}
  		},
  		animation: {
  			'accordion-down': 'accordion-down 0.2s ease-out',
  			'accordion-up': 'accordion-up 0.2s ease-out',
  			'terax-sheen': 'terax-sheen 5s linear infinite',
  			'terax-hop': 'terax-hop 1.6s ease-in-out infinite',
  			'terax-wiggle': 'terax-wiggle 3s ease-in-out infinite',
  			'terax-shine': 'terax-shine 3.5s ease-in-out infinite'
  		}
  	}
  },
  plugins: [require("tailwindcss-animate")],
}