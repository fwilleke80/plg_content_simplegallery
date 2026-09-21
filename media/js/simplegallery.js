/**
 * Initializes all Punga Simple Gallery sliders and built-in lightboxes.
 *
 * @return {void}
 */
document.addEventListener('DOMContentLoaded', function ()
{
	InitializeSimpleGallerySliders();
	InitializeSimpleGalleryLightboxes();
});

/**
 * Initializes slider layouts.
 *
 * @return {void}
 */
function InitializeSimpleGallerySliders()
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

		/**
		 * Updates the currently visible slide.
		 *
		 * @return {void}
		 */
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
			if (event.key === 'ArrowLeft' && currentIndex > 0)
			{
				currentIndex--;
				UpdateSlider();
			}
			else if (event.key === 'ArrowRight' && currentIndex < slides.length - 1)
			{
				currentIndex++;
				UpdateSlider();
			}
		});

		UpdateSlider();
	});
}

/**
 * Initializes the built-in lightbox for all galleries on the page.
 *
 * @return {void}
 */
function InitializeSimpleGalleryLightboxes()
{
	const galleries = document.querySelectorAll('.simplegallery-gallery[data-simplegallery-gallery]');

	galleries.forEach(function (gallery)
	{
		const links = Array.from(gallery.querySelectorAll('a[data-simplegallery-item]'));

		if (links.length === 0)
		{
			return;
		}

		links.forEach(function (link, index)
		{
			link.addEventListener('click', function (event)
			{
				event.preventDefault();
				OpenSimpleGalleryLightbox(gallery, links, index, link);
			});
		});
	});
}

/**
 * Opens a lightbox for one gallery.
 *
 * @param {HTMLElement} gallery Gallery root element.
 * @param {HTMLAnchorElement[]} links Gallery media links.
 * @param {number} startIndex Initial item index.
 * @param {HTMLElement} returnFocus Element that should regain focus when closing.
 * @return {void}
 */
function OpenSimpleGalleryLightbox(gallery, links, startIndex, returnFocus)
{
	const overlay = document.createElement('div');
	const dialog = document.createElement('div');
	const stage = document.createElement('div');
	const mediaContainer = document.createElement('div');
	const info = document.createElement('div');
	const galleryLabel = document.createElement('div');
	const title = document.createElement('h2');
	const description = document.createElement('p');
	const metadata = document.createElement('dl');
	const counter = document.createElement('div');
	const closeButton = document.createElement('button');
	const previousButton = document.createElement('button');
	const nextButton = document.createElement('button');
	const closeLabel = gallery.dataset.simplegalleryLabelClose || 'Close';
	const previousLabel = gallery.dataset.simplegalleryLabelPrevious || 'Previous';
	const nextLabel = gallery.dataset.simplegalleryLabelNext || 'Next';
	const dialogLabel = gallery.dataset.simplegalleryLabelDialog || 'Media viewer';
	let currentIndex = Math.max(0, Math.min(startIndex, links.length - 1));
	let pointerStartX = null;

	overlay.className = 'simplegallery-lightbox';
	overlay.setAttribute('data-simplegallery-lightbox', '');

	dialog.className = 'simplegallery-lightbox-dialog';
	dialog.setAttribute('role', 'dialog');
	dialog.setAttribute('aria-modal', 'true');
	dialog.setAttribute('aria-label', dialogLabel);
	dialog.setAttribute('tabindex', '-1');

	stage.className = 'simplegallery-lightbox-stage';
	mediaContainer.className = 'simplegallery-lightbox-media';
	info.className = 'simplegallery-lightbox-info';
	galleryLabel.className = 'simplegallery-lightbox-gallery-title';
	title.className = 'simplegallery-lightbox-title';
	description.className = 'simplegallery-lightbox-description';
	metadata.className = 'simplegallery-lightbox-metadata';
	counter.className = 'simplegallery-lightbox-counter';

	closeButton.type = 'button';
	closeButton.className = 'simplegallery-lightbox-close';
	closeButton.setAttribute('aria-label', closeLabel);
	closeButton.innerHTML = '&times;';

	previousButton.type = 'button';
	previousButton.className = 'simplegallery-lightbox-nav simplegallery-lightbox-prev';
	previousButton.setAttribute('aria-label', previousLabel);
	previousButton.innerHTML = '&#10094;';

	nextButton.type = 'button';
	nextButton.className = 'simplegallery-lightbox-nav simplegallery-lightbox-next';
	nextButton.setAttribute('aria-label', nextLabel);
	nextButton.innerHTML = '&#10095;';

	stage.appendChild(mediaContainer);
	stage.appendChild(previousButton);
	stage.appendChild(nextButton);
	info.appendChild(galleryLabel);
	info.appendChild(title);
	info.appendChild(description);
	info.appendChild(metadata);
	info.appendChild(counter);
	dialog.appendChild(closeButton);
	dialog.appendChild(stage);
	dialog.appendChild(info);
	overlay.appendChild(dialog);
	document.body.appendChild(overlay);
	document.body.classList.add('simplegallery-lightbox-open');

	/**
	 * Parses the JSON payload stored on one gallery link.
	 *
	 * @param {HTMLAnchorElement} link Gallery media link.
	 * @return {Object}
	 */
	const ParsePayload = function (link)
	{
		try
		{
			return JSON.parse(link.dataset.simplegalleryPayload || '{}');
		}
		catch (error)
		{
			return (
			{
				type: 'image',
				src: link.href,
				poster: '',
				title: '',
				description: '',
				galleryTitle: '',
				metadata: []
			}
			);
		}
	};

	/**
	 * Stops and removes the currently displayed media element.
	 *
	 * @return {void}
	 */
	const ClearMedia = function ()
	{
		const video = mediaContainer.querySelector('video');

		if (video)
		{
			video.pause();
			video.removeAttribute('src');
			video.load();
		}

		mediaContainer.replaceChildren();
	};

	/**
	 * Preloads neighboring images for faster navigation.
	 *
	 * @param {number} index Current item index.
	 * @return {void}
	 */
	const PreloadNeighbors = function (index)
	{
		[index - 1, index + 1].forEach(function (neighborIndex)
		{
			if (neighborIndex < 0 || neighborIndex >= links.length)
			{
				return;
			}

			const payload = ParsePayload(links[neighborIndex]);

			if (payload.type === 'image' && payload.src)
			{
				const image = new Image();
				image.src = payload.src;
			}
		});
	};

	/**
	 * Renders one media item into the lightbox.
	 *
	 * @param {number} index Item index.
	 * @return {void}
	 */
	const RenderItem = function (index)
	{
		currentIndex = Math.max(0, Math.min(index, links.length - 1));
		const payload = ParsePayload(links[currentIndex]);
		ClearMedia();

		if (payload.type === 'video')
		{
			const video = document.createElement('video');
			video.className = 'simplegallery-lightbox-video';
			video.controls = true;
			video.autoplay = true;
			video.playsInline = true;
			video.preload = 'metadata';
			video.src = payload.src || links[currentIndex].href;

			if (payload.poster)
			{
				video.poster = payload.poster;
			}

			mediaContainer.appendChild(video);
		}
		else
		{
			const image = document.createElement('img');
			image.className = 'simplegallery-lightbox-image';
			image.src = payload.src || links[currentIndex].href;
			image.alt = payload.title || '';
			mediaContainer.appendChild(image);
		}

		galleryLabel.textContent = payload.galleryTitle || '';
		galleryLabel.hidden = !payload.galleryTitle;
		title.textContent = payload.title || '';
		title.hidden = !payload.title;
		description.textContent = payload.description || '';
		description.hidden = !payload.description;
		metadata.replaceChildren();

		if (Array.isArray(payload.metadata))
		{
			payload.metadata.forEach(function (row)
			{
				if (!row || !row.value)
				{
					return;
				}

				const term = document.createElement('dt');
				const value = document.createElement('dd');
				term.textContent = row.label || '';
				value.textContent = row.value;
				metadata.appendChild(term);
				metadata.appendChild(value);
			});
		}

		metadata.hidden = metadata.children.length === 0;
		counter.textContent = (currentIndex + 1) + ' / ' + links.length;
		previousButton.disabled = currentIndex === 0;
		nextButton.disabled = currentIndex >= links.length - 1;
		PreloadNeighbors(currentIndex);
	};

	/**
	 * Closes the lightbox and restores focus.
	 *
	 * @return {void}
	 */
	const Close = function ()
	{
		ClearMedia();
		document.body.classList.remove('simplegallery-lightbox-open');
		overlay.remove();
		document.removeEventListener('keydown', HandleKeyDown);

		if (returnFocus && typeof returnFocus.focus === 'function')
		{
			returnFocus.focus();
		}
	};

	/**
	 * Handles lightbox keyboard interaction.
	 *
	 * @param {KeyboardEvent} event Keyboard event.
	 * @return {void}
	 */
	const HandleKeyDown = function (event)
	{
		if (event.key === 'Escape')
		{
			event.preventDefault();
			Close();
			return;
		}

		if (event.key === 'ArrowLeft' && currentIndex > 0)
		{
			event.preventDefault();
			RenderItem(currentIndex - 1);
			return;
		}

		if (event.key === 'ArrowRight' && currentIndex < links.length - 1)
		{
			event.preventDefault();
			RenderItem(currentIndex + 1);
			return;
		}

		if (event.key === 'Tab')
		{
			const focusable = Array.from(dialog.querySelectorAll('button:not(:disabled), video[controls], [tabindex]:not([tabindex="-1"])'));

			if (focusable.length === 0)
			{
				event.preventDefault();
				return;
			}

			const first = focusable[0];
			const last = focusable[focusable.length - 1];

			if (event.shiftKey && document.activeElement === first)
			{
				event.preventDefault();
				last.focus();
			}
			else if (!event.shiftKey && document.activeElement === last)
			{
				event.preventDefault();
				first.focus();
			}
		}
	};

	closeButton.addEventListener('click', Close);
	previousButton.addEventListener('click', function ()
	{
		if (currentIndex > 0)
		{
			RenderItem(currentIndex - 1);
		}
	});
	nextButton.addEventListener('click', function ()
	{
		if (currentIndex < links.length - 1)
		{
			RenderItem(currentIndex + 1);
		}
	});

	overlay.addEventListener('click', function (event)
	{
		if (event.target === overlay)
		{
			Close();
		}
	});

	stage.addEventListener('pointerdown', function (event)
	{
		pointerStartX = event.clientX;
	});

	stage.addEventListener('pointerup', function (event)
	{
		if (pointerStartX === null)
		{
			return;
		}

		const delta = event.clientX - pointerStartX;
		pointerStartX = null;

		if (Math.abs(delta) < 60)
		{
			return;
		}

		if (delta > 0 && currentIndex > 0)
		{
			RenderItem(currentIndex - 1);
		}
		else if (delta < 0 && currentIndex < links.length - 1)
		{
			RenderItem(currentIndex + 1);
		}
	});

	document.addEventListener('keydown', HandleKeyDown);
	RenderItem(currentIndex);
	closeButton.focus();
}
