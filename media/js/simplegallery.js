document.addEventListener('DOMContentLoaded', function ()
{
	const sliders = document.querySelectorAll('.simplegallery-slider[data-simplegallery-slider]');

	sliders.forEach(function (slider)
	{
		const track = slider.querySelector('.simplegallery-slider-track');
		const slides = Array.from(slider.querySelectorAll('.simplegallery-slide'));
		const prevButton = slider.querySelector('.simplegallery-slider-arrow-prev');
		const nextButton = slider.querySelector('.simplegallery-slider-arrow-next');
		const dots = Array.from(slider.querySelectorAll('.simplegallery-slider-dot'));

		if (!track || slides.length === 0)
		{
			return;
		}

		let currentIndex = 0;

		const UpdateSlider = function ()
		{
			track.style.transform = 'translateX(-' + (currentIndex * 100) + '%)';

			dots.forEach(function (dot, index)
			{
				if (index === currentIndex)
				{
					dot.classList.add('is-active');
					dot.setAttribute('aria-current', 'true');
				}
				else
				{
					dot.classList.remove('is-active');
					dot.removeAttribute('aria-current');
				}
			});

			if (prevButton)
			{
				prevButton.disabled = currentIndex === 0;
			}

			if (nextButton)
			{
				nextButton.disabled = currentIndex >= slides.length - 1;
			}
		};

		if (prevButton)
		{
			prevButton.addEventListener('click', function ()
			{
				if (currentIndex > 0)
				{
					currentIndex--;
					UpdateSlider();
				}
			});
		}

		if (nextButton)
		{
			nextButton.addEventListener('click', function ()
			{
				if (currentIndex < slides.length - 1)
				{
					currentIndex++;
					UpdateSlider();
				}
			});
		}

		dots.forEach(function (dot, index)
		{
			dot.addEventListener('click', function ()
			{
				currentIndex = index;
				UpdateSlider();
			});
		});

		slider.addEventListener('keydown', function (event)
		{
			if (event.key === 'ArrowLeft')
			{
				if (currentIndex > 0)
				{
					currentIndex--;
					UpdateSlider();
				}
			}
			else if (event.key === 'ArrowRight')
			{
				if (currentIndex < slides.length - 1)
				{
					currentIndex++;
					UpdateSlider();
				}
			}
		});

		UpdateSlider();
	});
});