"""
Custom middleware for Video Portal.
"""
import time
import psutil
import os
from django.db import connection
from django.conf import settings


class PerformanceMiddleware:
    """Middleware to track performance metrics."""
    
    def __init__(self, get_response):
        self.get_response = get_response
    
    def __call__(self, request):
        # Start timing
        start_time = time.time()
        
        # Get initial memory usage
        process = psutil.Process(os.getpid())
        start_memory = process.memory_info().rss / 1024 / 1024  # MB
        
        # Get initial query count
        initial_queries = len(connection.queries)
        
        # Process request
        response = self.get_response(request)
        
        # Calculate metrics
        end_time = time.time()
        generation_time = (end_time - start_time) * 1000  # Convert to milliseconds
        
        # Get final memory usage
        end_memory = process.memory_info().rss / 1024 / 1024  # MB
        memory_usage = end_memory - start_memory
        
        # Get final query count
        final_queries = len(connection.queries)
        query_count = final_queries - initial_queries
        
        # Add metrics to request
        request.timer = round(generation_time, 2)
        request.query_count = query_count
        request.memory_usage = round(memory_usage, 2)
        
        return response
