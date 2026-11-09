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
  			'terax-float': {
  				'0%, 100%': { transform: 'translateY(0)' },
  				'50%': { transform: 'translateY(-14px)' }
  			},
  			'terax-glow': {
  				'0%, 100%': { transform: 'scale(1)', boxShadow: '0 0 0 0 rgba(255,255,255,0.35)' },
  				'50%': { transform: 'scale(1.06)', boxShadow: '0 0 0 12px rgba(255,255,255,0)' }
  			},
  			'terax-shine': {
  				'0%': { transform: 'translateX(-150%) skewX(-20deg)' },
  				'55%, 100%': { transform: 'translateX(450%) skewX(-20deg)' }
  			},
  			'terax-rise': {
  				from: { opacity: '0', transform: 'translateY(8px)' },
  				to: { opacity: '1', transform: 'translateY(0)' }
  			}
  		},
  		animation: {
  			'terax-float': 'terax-float 7s ease-in-out infinite',
  			'terax-float-slow': 'terax-float 10s ease-in-out infinite reverse',
  			'terax-glow': 'terax-glow 2.8s ease-out infinite',
  			'terax-shine': 'terax-shine 4s ease-in-out infinite',
  			'terax-rise': 'terax-rise 0.5s ease-out both',
  			'accordion-down': 'accordion-down 0.2s ease-out',
  			'accordion-up': 'accordion-up 0.2s ease-out'
  		}
  	}
  },
  plugins: [require("tailwindcss-animate")],
}