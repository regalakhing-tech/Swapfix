# Reviews Module

Customers can rate a completed repair from `/repairs/{id}` using one to five stars and an optional comment. The private repair page submits to `POST /repairs/{id}/review`. The database checks that the signed-in customer owns the request, the assigned technician completed it, and a review does not already exist.

Public technician profiles at `/technicians/{id}` show the aggregate rating and the latest written feedback. Public review data includes the customer's display name, rating, comment, and submission date.
