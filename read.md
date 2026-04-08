# PHP Web Crawler & Search Engine

## Overview
This project is a custom-built PHP web crawler and indexing engine that scans websites, extracts structured data, and stores it in a MySQL database for search and analysis.

## Features
- Crawls websites and extracts:
  - Headers (H1–H6)
  - Paragraph text
  - Images (with alt text)
  - Videos
  - Links
- Stores structured data in relational database
- Multi-stage search system:
  - Exact match
  - Partial match
  - Ranked results
- Domain-based filtering
- Continuous scanning system
- Error logging and debugging system

## Technologies Used
- PHP
- MySQL
- JavaScript / jQuery
- HTML

## How It Works
1. URLs are loaded from the database
2. Pages are fetched and parsed using DOMDocument
3. Content is extracted and stored in structured tables
4. Search system queries indexed data with prioritization logic

## Live Demo
Portfolio:
https://developments.danie.capeundercar.co.za/portfolio/php_scanner/

## Author
Daniel Petrus Brits