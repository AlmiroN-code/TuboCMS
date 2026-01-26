# Coding Standards

## PHP Standards
- **PHP 8.4** features, strict types, modern syntax
- **Doctrine attributes** (not annotations)
- **Route attributes**: `#[Route('/path', name: 'route_name')]`
- **Dependency injection** in constructors
- **camelCase** methods, **PascalCase** classes

## Frontend Standards  
- **Tailwind CSS** - use utility classes, no custom CSS
- **Proper Tailwind classes**: `px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500`
- **Stimulus controllers** for interactivity
- **Twig templates** - semantic HTML, accessibility

## Form Styling
- Input: `px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors`
- Select: Same + `bg-white`
- Checkbox: `w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500`
- Button: `inline-flex items-center px-4 py-2 border rounded-md shadow-sm text-sm font-medium transition-colors`

## Security
- CSRF protection, input validation, proper authentication
- Rate limiting, secure file uploads