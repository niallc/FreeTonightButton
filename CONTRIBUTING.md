# Contributing to Free Tonight Button

Thank you for your interest in contributing to this project! This is a simple, focused application for friends to coordinate social activities.

## Development Setup

1. **Clone the repository:**
   ```bash
   git clone https://github.com/niallc/FreeTonightButton.git
   cd FreeTonightButton
   ```

2. **Start the development server:**
   ```bash
   npm start
   # or
   php dev_server.php
   ```

3. **Run tests:**
   ```bash
   npm test
   # or
   ./run_all_tests.sh
   ```

## Project Structure

- `public/freetonight/` - Web-accessible files
- `private/freetonight/` - Database and logs (auto-created)
- `tests/` - Test suites
- `design.md` - Design documentation and future plans

## Making Changes

1. **Create a feature branch:**
   ```bash
   git checkout -b feature/your-feature-name
   ```

2. **Make your changes** following the existing code style

3. **Test your changes:**
   ```bash
   npm test
   ```

4. **Update documentation** if needed

5. **Submit a pull request** with a clear description

## Code Style

- **PHP:** Follow PSR-12 standards
- **JavaScript:** Use modern ES6+ features, consistent indentation
- **CSS:** Mobile-first, responsive design
- **HTML:** Semantic markup, accessibility-friendly

## Testing

All changes should include appropriate tests:
- Unit tests for new PHP functionality
- JavaScript tests for frontend changes
- HTTP integration tests for API changes

## Questions?

Feel free to open an issue for questions or discussions about the project direction. 